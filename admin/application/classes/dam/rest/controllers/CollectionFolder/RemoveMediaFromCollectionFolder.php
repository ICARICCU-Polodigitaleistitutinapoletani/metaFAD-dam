<?php

class dam_rest_controllers_CollectionFolder_RemoveMediaFromCollectionFolder extends pinax_rest_core_CommandRest
{


    function execute($instance, $type, $collectionFolderId, $mediaId)
    {
        try {
            if ($type != "collection" && $type != "folder") {
                throw new dam_exceptions_NotFound();
            }
            $collectionFolder = __ObjectFactory::createModel("dam.models.CollectionFolder");
            $media = __ObjectFactory::createModel("dam.models.Media");
            if ($collectionFolder->load($collectionFolderId) && $collectionFolder->instance == $instance && $collectionFolder->type == $type && $media->load($mediaId) && $media->instance == $instance) {
                if ($collectionFolder->media_id && is_array($collectionFolder->media_id) && in_array($mediaId, $collectionFolder->media_id)) {
                    $arr = $collectionFolder->media_id;
                    unset($arr[array_search($mediaId, $arr)]);
                    $collectionFolder->media_id = $arr;
                    $collectionFolder->publish();
                }
                if ($type == "collection") {
                    if ($media->collection && is_array($media->collection)) {
                        $count = 0;
                        $arr = $media->collection;
                        foreach ($media->collection as $collectionId) {
                            if ($collectionId == $collectionFolderId) {
                                unset($arr[$count]);
                                break;
                            }
                            $count++;
                        }
                        $media->collection = $arr;
                    }
                } else {
                    if ($media->folder == (string)$collectionFolderId) {
                        $media->folder = null;
                    }
                }
                $media->publish();
                $solrMapperHelper = __ObjectFactory::createObject("dam.helpers.SolrMapper");
                $document = $solrMapperHelper->mapMediaToSolr($media);
                $solrService = __ObjectFactory::createObject('dam.helpers.SolrService');
                $solrService->publish($document);
                return array("http-status" => 200);
            } else {
                throw new dam_exceptions_BadRequest("Bad request");
            }
        }
        catch (Exception $e){
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }
    }
}