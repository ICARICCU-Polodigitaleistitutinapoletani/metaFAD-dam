<?php
class dam_rest_controllers_Media_DeleteDataStream extends pinax_rest_core_CommandRest
{
    function execute($instance, $mediaId, $modelName, $modelId)
    {
        try {
            $media = __ObjectFactory::createModel("dam.models.Media");

            if ($instance) {
                if ($media->load($mediaId) && $media->instance == $instance) {
                    $data = json_decode(__Request::get('__postBody__'));
                    $dataStreamProxy = __ObjectFactory::createObject('dam.instance.models.proxy.DataStreamProxy', $modelName);

                    if ($modelId) {
                        $dataStreamProxy->load($modelId);
                    }

                    $comment = __Request::get('comment', '');
                    $dataStreamId = $dataStreamProxy->delete();

                    $doc = $media;
                    if (!$doc->datastream) {
                        $doc->datastream = array();
                    }
                    else
                    {
                        $key = array_search($modelId, $doc->datastream);
                        if($key)
                        {
                            $datastreamArr = $doc->datastream;
                            unset($datastreamArr[$key]);
                            $doc->datastream = $datastreamArr;
                            $doc->publish();
                        }
                    }

                    $solrMapperHelper = __ObjectFactory::createObject("dam.helpers.SolrMapper");
                    $document = $solrMapperHelper->mapMediaToSolr($media);
                    $solrService = __ObjectFactory::createObject('dam.helpers.SolrService');
                    $solrService->publish($document);
                    return $dataStreamProxy->getDataStreamVO();
                } else {
                    throw new dam_exceptions_BadRequest("Media doesn't exist in the instance");
                }
            } else {
                throw new dam_exceptions_BadRequest("Missing instance parameter");
            }
        } catch (Exception $e) {
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }
    }
}