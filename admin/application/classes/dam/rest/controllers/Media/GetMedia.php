<?php
class dam_rest_controllers_Media_GetMedia extends pinax_rest_core_CommandRest
{
    function execute($instance, $mediaId, $MainData, $datastream, $bytestream, $history, $collection, $folder, $RelatedMedia)
    {
        try {
            $queryParams = array("MainData", "datastream", "bytestream", "history", "collection", "folder", "RelatedMedia");
            $options = array();
            foreach($queryParams as $q){

                $requestValue = __Request::get($q);
                if($requestValue && $requestValue !== "false"){
                    $options[$q] = $requestValue;
                }
            }
            $media = __ObjectFactory::createModel("dam.models.Media");
            if($media->load($mediaId) && $media->media_type != "CONTAINER" && $media->instance = $instance){
                $response = __ObjectFactory::createObject("dam.rest.models.vo.MediaDetailVO", $media, $options);
                return $response;
            }
            else{
                throw new dam_exceptions_BadRequest("Media not found");
            }
        }
        catch(Exception $e){
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }
    }
}