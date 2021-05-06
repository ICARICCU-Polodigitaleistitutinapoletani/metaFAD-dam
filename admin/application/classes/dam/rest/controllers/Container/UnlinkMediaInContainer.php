<?php

class dam_rest_controllers_Container_UnlinkMediaInContainer extends pinax_rest_core_CommandRest
{

    function execute($instance, $containerId, $mediaType, $mediaId)
    {
        try {
            if($instance){
                if ($mediaType == "ContainedMedia" || $mediaType == "RelatedMedia") {
                    $container = __ObjectFactory::createModel("dam.models.Media");
                    $media = __ObjectFactory::createModel("dam.models.Media");
                    if ($container->load($containerId) && $container->instance == $instance && $media->load($mediaId) && $media->instance == $instance) {
                        if ($container->media_child && is_array($container->media_child) && in_array($media->uuid, $container->media_child)) {
                            $mediaChilds = $container->media_child;
                            $solrMapperHelper = __ObjectFactory::createObject("dam.helpers.SolrMapper");
                            $solrService = __ObjectFactory::createObject('dam.helpers.SolrService');
                            $unlinkType = __Request::get("removeFromDam");
                            $unlinkType = ($unlinkType === "true") ? true : false;
                            if(($mediaType == "ContainedMedia" && $media->is_contained == true) || ($mediaType == "RelatedMedia" && (!$media->is_contained || $media->is_contained != true))){
                                if ($mediaType == "ContainedMedia" && $unlinkType && $unlinkType == true) {
                                    $mediaHelper = __ObjectFactory::createObject("dam.helpers.Media", $media);
                                    $mediaHelper->delete();
                                }
                                else{
                                    unset($mediaChilds[array_search($media->uuid, $mediaChilds)]);
                                    if($media->media_parent && is_array($media->media_parent) && in_array($container->uuid, $media->media_parent)){
                                        $mediaParents = $media->media_parent;
                                        unset($mediaParents[array_search($container->uuid, $mediaParents)]);
                                        $media->media_parent = array_values($mediaParents);
                                        $media->is_contained = false;
                                        $media->publish();
                                        $solrDocument = $solrMapperHelper->mapMediaToSolr($media);
                                        $solrService->publish($solrDocument);
                                    }
                                    $container->media_child = array_values($mediaChilds);
                                    $container->publish();
                                    $solrDocument = $solrMapperHelper->mapMediaToSolr($container);
                                    $solrService->publish($solrDocument);
                                }
                            }
                            else{
                                throw new dam_exceptions_BadRequest("Operation not permitted on this media");
                            }
                            return array("http-status" => 200);
                        } else {
                            throw new dam_exceptions_BadRequest("Media is not in the container");
                        }
                    } else {
                        throw new dam_exceptions_BadRequest("Container or Media not exist in the instance");
                    }
                }
                else{
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