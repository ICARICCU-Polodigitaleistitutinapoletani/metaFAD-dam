<?php

class dam_rest_controllers_Container_GetContainer extends pinax_rest_core_CommandRest
{

    function execute($instance, $containerId, $MainData,  $collection, $folder, $RelatedMedia)
    {
        try {
            if($instance){
                $queryParams = array("MainData", "collection", "folder", "RelatedMedia", "ContainedMedia", "bytestream");
                $options = array();
                foreach($queryParams as $q){
                    $requestValue = __Request::get($q);
                    if($requestValue && $requestValue !== "false"){
                        $options[$q] = $requestValue;
                    }
                }
                $container = __ObjectFactory::createModel("dam.models.Media");
                if($container->load($containerId) && $container->media_type== "CONTAINER" && $container->instance = $instance){
                    $response = __ObjectFactory::createObject("dam.rest.models.vo.ContainerDetailVO", $container, $options);
                    return $response;
                }
                else{
                    throw new dam_exceptions_BadRequest("Container not exist in the instance");
                }
            }
            else{
                throw new dam_exceptions_BadRequest("Missing instance parameter");
            }
        }
        catch(Exception $e){
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }
    }
}