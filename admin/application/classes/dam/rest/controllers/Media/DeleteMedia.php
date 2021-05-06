<?php
set_time_limit(0);

class dam_rest_controllers_Media_DeleteMedia extends pinax_rest_core_CommandRest
{
    function execute($instance, $mediaId)
    {
        try {
            if ($instance) {
                $media = __ObjectFactory::createModel("dam.models.Media");
                if ($media->load($mediaId) && $media->instance == $instance){
                    $mediaHelper = __ObjectFactory::createObject("dam.helpers.Media", $media);
                    $mediaHelper->delete();
                    return array("http-status" => 200, 'message' => 'Media deleted');
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