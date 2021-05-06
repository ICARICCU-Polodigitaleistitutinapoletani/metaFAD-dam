<?php

class dam_rest_controllers_CollectionFolder_DeleteCollectionFolder extends pinax_rest_core_CommandRest
{


    function execute($instance, $type, $collectionFolderId)
    {
        try {
            if ($type != "collection" && $type != "folder") {
                throw new dam_exceptions_NotFound();
            }
            $collectionFolder = __ObjectFactory::createModel("dam.models.CollectionFolder");
            if ($collectionFolder->load($collectionFolderId) && $collectionFolder->instance == $instance && $collectionFolder->type == $type) {
                $media = __ObjectFactory::createModel("dam.models.Media");
                $allCollectionFolder = array_merge(array($collectionFolder), $this->getAllChilds((string)$collectionFolder->getId()));
                foreach ($allCollectionFolder as $collectionFolderToRemove) {
                    foreach ($collectionFolderToRemove->media_id as $mediaId) {
                        $media->emptyRecord(); //Déjà vu: fare empty sempre prima di Load in questi casi!
                        $media->load($mediaId);

                        if ($type == "collection") {
                            if ($media->collection && is_array($media->collection)) {
                                $count = 0;
                                $arr = $media->collection;
                                foreach ($media->collection as $collectionId) {
                                    if ($collectionId == $collectionFolderToRemove->getId()) {
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

                    }
                    $collectionFolderToRemove->delete();
                }
                return array("http-status" => 200, 'message' => 'Collection/folder deleted');
            } else {
                throw new dam_exceptions_BadRequest($type . " not found.");
            }
        }
        catch(Exception $e){
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }
    }

    function getAllChilds($collectionFolderId){
        $collectionFolderIterator = __ObjectFactory::createModelIterator("dam.models.CollectionFolder")
                                    ->where("parent", $collectionFolderId);

        if(!$collectionFolderIterator->count()){
            return array();
        }
        $arr = array();
        foreach($collectionFolderIterator as $collectionFolder){
            $arr[] = $collectionFolder;
            $childCollectionFolderId = $collectionFolder->getId();
            $arr = array_merge($arr, $this->getAllChilds($childCollectionFolderId));
        }
        return $arr;
    }
}