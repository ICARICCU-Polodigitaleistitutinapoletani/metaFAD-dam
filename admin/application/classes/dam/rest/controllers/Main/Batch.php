<?php
class dam_rest_controllers_Main_Batch extends pinax_rest_core_CommandRest
{
    protected $supportedActions = array(
        'flip' => array(null),
        'flop' => array(null),
        'rotate' => array('degrees'),
        'setImageFormat' => array('format'),
        'resampleImage' => array('xResolution', 'yResolution', 'maintainAspect', 'resize'),
        'crop' => array('width', 'height', 'x', 'y'),
        'resize' =>array('width', 'height')
    );

    function execute($instance)
    {
        try {
            if ($instance) {
                $data = json_decode(__Request::get('__postBody__'));
                if ($data->medias || $data->mediaSearch) {
                    $mediaIds = $data->medias ? $data->medias : array();

                    if ($data->mediaSearch) {
                        $searchService = __ObjectFactory::createObject('dam.services.SearchService', $instance);
                        $solrResult = $searchService->doSolrSearch($instance, $data->mediaSearch->search, $data->mediaSearch->filters);

                        foreach ($solrResult->results as $result) {
                            $mediaIds[] = $result->id;
                        }
                    }

                    $batchActions = array();

                    foreach ($mediaIds as $mediaId) {
                        $batchActions[] = $this->getBachParams($instance, $mediaId, $data);
                    }

                    $jobFactory = pinax_objectFactory::createObject('dam.jobmanager.JobFactory');
                    $media = __ObjectFactory::createModel('dam.models.Media');
                    $solrMapperHelper = __ObjectFactory::createObject('dam.helpers.SolrMapper');
                    $solrService = __ObjectFactory::createObject('dam.helpers.SolrService');

                    foreach ($batchActions as $batchAction) {
                        $jobFactory->createJob('dam.helpers.Batch', array('data' => $batchAction), 'Descrizione della operazione batch', 'BACKGROUND');
                        $media->load($batchAction->media_id);
                        $solrDocument = $solrMapperHelper->mapMediaToSolr($media);
                        $solrDocument->bytestream_batch_s = true;
                        $solrService->publish($solrDocument);
                    }
                    return array('http-status' => 201, 'message' => 'Jobs created');
                } else {
                    throw new dam_exceptions_BadRequest('Missign one or more media id');
                }
            } else {
                throw new dam_exceptions_BadRequest('Missing instance parameter');
            }
        }
        catch (Exception $e) {
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }
    }

    protected function getBachParams($instance, $mediaId, $data)
    {
        $media = __ObjectFactory::createModel('dam.models.Media');
        if ($media->load($mediaId) && $media->instance == $instance) {
            $batchParams = new stdClass();
            $batchParams->instance = $media->instance;
            $batchParams->media_id = $media->uuid;
            $batchParams->name = $data->bytestreamName ? $data->bytestreamName : 'original';
            $batchParams->new_name = $data->bytestreamNewName;
            $batchParams->media_type = $media->media_type;
            foreach ($data->actions as $action) {
                if (array_key_exists($action->type, $this->supportedActions)) {
                    if (isset($action->parameters)) {
                        foreach ($action->parameters as $key => $value) {
                            if (!in_array($key, $this->supportedActions[$action->type])) {
                                throw  new dam_exceptions_BadRequest('Bad action parameter for media with id ' . $media->uuid);
                            }
                        }
                    } else if ($action->type != 'flip' && $action->type != 'flop') {
                        throw  new dam_exceptions_BadRequest('Bad action parameter for media with id ' . $media->uuid);
                    }
                    $batchParams->{$action->type} = $action->parameters;
                } else {
                    throw new dam_exceptions_BadRequest('Bad action type for media with id ' . $media->uuid);
                }
            }
            return $batchParams;
        } else {
            throw new dam_exceptions_BadRequest('Media with id ' . $media->uuid . ' not in the instance');
        }
    }
}
