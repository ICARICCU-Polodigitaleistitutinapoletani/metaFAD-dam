<?php
set_time_limit(0);

class dam_rest_controllers_Media_RemoveMedias extends pinax_rest_core_CommandRest
{
    function execute($instance)
    {
        try {
            $data = json_decode(__Request::get('__postBody__'));
            if ($instance) {
                if ($data->medias) {
                    foreach ($data->medias as $mediaId) {
                        $this->deleteMedia($instance, $mediaId);
                    }
                }

                if ($data->mediaSearch) {
                    $searchService = __ObjectFactory::createObject('dam.services.SearchService', $instance);
                    $solrResult = $searchService->doSolrSearch($instance, $data->mediaSearch->search, $data->mediaSearch->filters, null, 1, null, 100000000, false);
                    foreach ($solrResult->results as $result) {
                        $this->deleteMedia($instance, $result->id);
                    }
                }

                return array("http-status" => 200, 'message' => 'Medias deleted');
            } else {
                throw new dam_exceptions_BadRequest("Missing instance parameter");
            }
        } catch (Exception $e) {
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }
    }

    function deleteMedia($instance, $mediaId)
    {
        $media = __ObjectFactory::createModel("dam.models.Media");
        if ($media->load($mediaId) && $media->instance == $instance){
            $mediaHelper = __ObjectFactory::createObject("dam.helpers.Media", $media);
            $mediaHelper->delete();
        } else {
            throw new dam_exceptions_BadRequest("Media doesn't exist in the instance");
        }
    }
}