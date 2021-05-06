<?php

class dam_rest_controllers_CollectionFolder_AddMediaToCollectionFolder extends pinax_rest_core_CommandRest
{


    function execute($instance, $type, $collectionFolderId)
    {

        try {
            if ($type != "collection" && $type != "folder") {
                throw new dam_exceptions_NotFound();
            }
            $collectionFolder = __ObjectFactory::createModel("dam.models.CollectionFolder");
            $media = __ObjectFactory::createModel("dam.models.Media");
            if ($collectionFolder->load($collectionFolderId) && $collectionFolder->instance == $instance && $collectionFolder->type == $type) {
                $data = json_decode(__Request::get('__postBody__'));
                if ($data && $data->addMedias && is_array($data->addMedias)) {
                    foreach ($data->addMedias as $mediaToAdd) {
                        $media->emptyRecord();
                        if ($mediaToAdd && $media->load($mediaToAdd)) {
                            if (!in_array($mediaToAdd, $collectionFolder->media_id)) {
                                $arr = $collectionFolder->media_id;
                                $arr[] = $mediaToAdd;
                                $collectionFolder->media_id = $arr;
                                $collectionFolder->publish();
                            }
                            $collectionFolderId = $collectionFolder->getId();
                            $collectionFolderId = (string)$collectionFolderId;
                            if ($type == "collection") {
                                if(!is_array($media->collection) || (is_array($media->collection) && !in_array($collectionFolderId, $media->collection))){
                                    $arr = $media->collection;
                                    $arr[] = $collectionFolderId;
                                    $media->collection = $arr;
                                }
                            } else {
                                $media->folder = $collectionFolder->getId();
                            }
                            $media->publish();
                            $solrMapperHelper = __ObjectFactory::createObject("dam.helpers.SolrMapper");
                            $document = $solrMapperHelper->mapMediaToSolr($media);
                            $solrService = __ObjectFactory::createObject('dam.helpers.SolrService');
                            $solrService->publish($document);

                        } else {
                            throw new dam_exceptions_BadRequest("Media $mediaToAdd was not correctly loaded. Aborting.");
                        }
                    }
                } else if ($data && !$data->addMedias) {
                    throw new dam_exceptions_BadRequest("Unexpected POST body: expecting at least one mediaId to add, got ".__Request::get('__postBody__'));

                } else {
                     throw new dam_exceptions_BadRequest("Unexpected or malformed POST body: expecting {\"addMedia\":[\"id1\",...]}, got ".__Request::get('__postBody__'));
                }


            } else {
                throw new dam_exceptions_BadRequest($type . " not found.");
            }
        }
        catch(Exception $e){
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }
        return array("http-status" => 201);
    }

}