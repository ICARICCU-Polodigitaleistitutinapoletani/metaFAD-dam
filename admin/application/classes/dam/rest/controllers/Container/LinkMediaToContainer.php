<?php

class dam_rest_controllers_Container_LinkMediaToContainer extends pinax_rest_core_CommandRest
{

    function execute($instance, $containerId, $mediaType)
    {
        set_time_limit(0);
        try {
            if($instance) {
                if ($mediaType == "ContainedMedia" || $mediaType == "RelatedMedia") {
                    $container = __ObjectFactory::createModel("dam.models.Media");
                    if($container->load($containerId) && $container->instance == $instance){
                        $data = json_decode(__Request::get('__postBody__'));
                        $solrMapperHelper = __ObjectFactory::createObject("dam.helpers.SolrMapper");
                        $solrService = __ObjectFactory::createObject('dam.helpers.SolrService');
                        if($container->media_child && is_array($container->media_child)){
                            $mediaChilds = $container->media_child;
                        }
                        else{
                            $mediaChilds = array();
                        }
                        if($data->addMedias && is_array($data->addMedias)){
                            foreach($data->addMedias as $mediaId){
                                $media = __ObjectFactory::createModel("dam.models.Media");
                                if($media->load($mediaId) && $media->instance == $container->instance){
                                    if($media->media_parent && is_array($media->media_parent)){
                                        $mediaParents = $media->media_parent;
                                    }
                                    else{
                                        $mediaParents = array();
                                    }
                                    if(!in_array($media->uuid, $mediaChilds)){
                                        $mediaChilds[] = $media->uuid;
                                        if(!in_array($container->uuid, $mediaParents)){
                                            $mediaParents[] = $container->uuid;
                                        }
                                    }
                                    $media->media_parent = $mediaParents;
                                    if($mediaType == "ContainedMedia") {
                                        $media->is_contained = true;
                                    }
                                    $media->publish();
                                    $solrDocument = $solrMapperHelper->mapMediaToSolr($media);
                                    $solrService->publish($solrDocument);
                                }
                            }
                            $container->media_child = $mediaChilds;
                            $container->publish();
                            $solrDocument = $solrMapperHelper->mapMediaToSolr($container);
                            $solrService->publish($solrDocument);
                            $response = new stdClass();
                            $response->ids = $mediaChilds;
                            $response->httpStatus = 201;
                            return $response;
                        }
                        else{
                            throw new dam_exceptions_BadRequest("Missing parameter in body");
                        }
                    }
                    else{
                        throw new dam_exceptions_BadRequest("Container not exist in the instance");
                    }
                } else {
                    throw new dam_exceptions_NotFound();
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
