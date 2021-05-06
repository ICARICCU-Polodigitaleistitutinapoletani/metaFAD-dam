<?php
class dam_rest_controllers_Container_DeleteContainer extends pinax_rest_core_CommandRest
{
    function execute($instance, $containerId, $removeContainedMedia)
    {
        try {
            if ($instance) {
                $container = __ObjectFactory::createModel("dam.models.Media");
                if ($container->load($containerId) && $container->instance == $instance) {
                    if ($removeContainedMedia == 'true') {
                        if ($container->media_child && is_array($container->media_child)) {
                            $media = __ObjectFactory::createModel("dam.models.Media");
                            foreach ($container->media_child as $mediaId) {
                                if ($media->load($mediaId) && $media->is_contained == true) {
                                    $mediaHelper = __ObjectFactory::createObject("dam.helpers.Media", $media);
                                    $mediaHelper->delete();
                                }
                            }
                        }
                    }
                    $mediaHelper = __ObjectFactory::createObject("dam.helpers.Media", $container);
                    $mediaHelper->delete();
                    return array("http-status" => 200, 'message' => 'Container deleted');
                } else {
                    throw new dam_exceptions_BadRequest("Container not exist in the instance");
                }
            } else {
                throw new dam_exceptions_BadRequest("Missing instance parameter");
            }
        } catch(Exception $e) {
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }
    }
}