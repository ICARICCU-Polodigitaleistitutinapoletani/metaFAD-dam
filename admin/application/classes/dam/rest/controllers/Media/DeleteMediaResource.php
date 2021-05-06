<?php


class dam_rest_controllers_Media_DeleteMediaResource extends pinax_rest_core_CommandRest
{

    function execute($instance, $mediaId, $modelName, $modelId)
    {
        try {
            $media = __ObjectFactory::createModel("dam.models.Media");
            if ($instance) {
                if ($media->load($mediaId) && $media->instance == $instance) {
                    $solrMapperHelper = __ObjectFactory::createObject("dam.helpers.SolrMapper");
                    $solrService = __ObjectFactory::createObject('dam.helpers.SolrService');
                    if ($modelName == "RelatedMedia") {
                        if ($media->media_child && is_array($media->media_child) && in_array($modelId, $media->media_child)) {
                            $mediaChild = __ObjectFactory::createModel("dam.models.Media");
                            if ($mediaChild->load($modelId)) {

                                // Delete from parent media
                                $childArray = $media->media_child;
                                unset($childArray[array_search($mediaChild->getId(), $childArray)]);
                                $media->media_child = $childArray;
                                $media->publish();
                                $document = $solrMapperHelper->mapMediaToSolr($media);
                                $solrService->publish($document);

                                // Delete from child media
                                $parentArray = $mediaChild->media_parent;
                                unset($parentArray[array_search($mediaChild->getId(), $parentArray)]);
                                $mediaChild->media_parent = $parentArray;
                                $mediaChild->publish();
                                $document = $solrMapperHelper->mapMediaToSolr($mediaChild);
                                $solrService->publish($document);
                            } else {
                                throw new dam_exceptions_InternalServerError("Cannot load the child media");
                            }
                        } else {
                            throw new dam_exceptions_BadRequest("Media doesn't have the child");
                        }
                    } else if ($modelName == "bytestream") {
                        $bytestream = __ObjectFactory::createModel("dam.models.ByteStream");
                        if ($bytestream->load($modelId) && in_array($bytestream->getId(), $media->bytestream)) {
                            $arr = $media->bytestream;
                            unset($arr[array_search($bytestream->getId(), $arr)]);
                            $bytestreamHelper = __ObjectFactory::createObject("dam.helpers.ByteStream");
                            $bytestreamHelper->deleteByAr($bytestream);
                            $media->bytestream = $arr;
                            $media->publish();
                            $document = $solrMapperHelper->mapMediaToSolr($media);
                            $solrService->publish($document);
                        } else {
                            throw new dam_exceptions_BadRequest("The bytestream is not related to the media");
                        }
                    } else {
                        throw new dam_exceptions_BadRequest("Bad request");
                    }
                } else {
                    throw new dam_exceptions_BadRequest("Media soesn't exist in the instance");
                }
            } else {
                throw new dam_exceptions_BadRequest("Missing instance parameter");
            }
        } catch (Exception $e) {
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }
    }
}