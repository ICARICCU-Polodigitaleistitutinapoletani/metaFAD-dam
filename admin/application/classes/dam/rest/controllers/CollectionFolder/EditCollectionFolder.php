<?php

class dam_rest_controllers_CollectionFolder_EditCollectionFolder extends pinax_rest_core_CommandRest
{

    function execute($instance, $type, $collectionFolderId)
    {
        try {
            if ($type != "collection" && $type != "folder") {
                throw new dam_exceptions_NotFound();
            }
            $data = json_decode(__Request::get('__postBody__'));
            if (!$data || !$data->title || (!$data->idParent && $data->idParent !== "0")) {
                throw new dam_exceptions_BadRequest("Missing parameter in body");
            }
            $collectionFolder = __ObjectFactory::createModel("dam.models.CollectionFolder");
            $treePathHelper = __ObjectFactory::createObject("dam.helpers.TreePath");
            $found = __ObjectFactory::createModelIterator("dam.models.CollectionFolder")
                ->where("parent", $data->idParent)
                ->where("type", $type)
                ->where("title", $data->title)
                ->where("instance", $instance)
                ->count();

            if (!$found) {
                if ($collectionFolder->load($collectionFolderId) && $collectionFolder->instance == $instance && $collectionFolder->type == $type) {
                    $collectionFolder->title = $data->title;
                    $collectionFolder->parent = $data->idParent;
                    $allCollectionFolderToChange =  $treePathHelper->getAllChildNode($collectionFolder);
                    $media = __ObjectFactory::createModel("dam.models.Media");
                    $solrMapperHelper = __ObjectFactory::createObject("dam.helpers.SolrMapper");
                    foreach($allCollectionFolderToChange as $collectionFolderToChange) {
                        $collectionFolderToChange->path = $treePathHelper->getPath($collectionFolderToChange);
                        $collectionFolderToChange->publish();
                        if (is_array($collectionFolderToChange->media_id)) {
                            foreach ($collectionFolderToChange->media_id as $mediaId) {
                                if ($media->load($mediaId)) {
                                    $document = $solrMapperHelper->mapMediaToSolr($media);
                                    $solrService = __ObjectFactory::createObject('dam.helpers.SolrService');
                                    $solrService->publish($document);
                                }
                            }
                        }
                    }
                    return array("http-status" => 200, 'message' => 'Updated');
                } else {
                    throw new dam_exceptions_BadRequest($type . " not found.");
                }
            }
            else{
                throw new dam_exceptions_BadRequest("A " . $type . " with same title already exist in this level");
            }
        }
        catch(Exception $e){
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }
    }
}
