<?php
class dam_rest_controllers_CollectionFolder_GetCollectionFolderChildren extends pinax_rest_core_CommandRest
{
    function execute($instance, $type, $parentCollectionFolderId)
    {
        try {
            if ($type != "collection" && $type != "folder") {
                throw new dam_exceptions_NotFound();
            }
            $collectionFolderIterator = __ObjectFactory::createModelIterator("dam.models.CollectionFolder")
                ->where('instance', $instance)
                ->where("parent", $parentCollectionFolderId)
                ->where("type", $type);
            $response = array();
            foreach ($collectionFolderIterator as $collectionFolder) {
                $collectionFolderItem = __ObjectFactory::createObject("dam.rest.models.vo.CollectionFolderVO");
                $collectionFolderItem->createFromDocument($collectionFolder);
                $response[] = $collectionFolderItem;
            }
            return array($response);
        }
        catch(Exception $e){
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }
    }
}