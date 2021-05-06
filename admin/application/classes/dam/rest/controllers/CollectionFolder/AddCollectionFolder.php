<?php

class dam_rest_controllers_CollectionFolder_AddCollectionFolder extends pinax_rest_core_CommandRest
{

    function execute($instance, $type)
    {
        try {
            if ($type != "collection" && $type != "folder") {
                throw new dam_exceptions_NotFound();
            }
            $data = json_decode(__Request::get('__postBody__'));
            if (!$data || !$data->title || (!$data->idParent && $data->idParent !== "0")) {
                throw new dam_exceptions_BadRequest("Bad request");
            }
            $found = __ObjectFactory::createModelIterator("dam.models.CollectionFolder")
                ->where("parent", $data->idParent)
                ->where("type", $type)
                ->where("title", $data->title)
                ->where("instance", $instance)
                ->count();
            if (!$found) {
                $newCollectionFolder = __ObjectFactory::createModel("dam.models.CollectionFolder");
                $treePathHelper = __ObjectFactory::createObject("dam.helpers.TreePath");
                $newCollectionFolder->title = $data->title;
                $newCollectionFolder->parent = $data->idParent;
                $newCollectionFolder->instance = $instance;
                $newCollectionFolder->type = $type;
                $newCollectionFolder->media_id = array();
                $newCollectionFolder->path = $treePathHelper->getPath($newCollectionFolder);
                $newCollectionFolder->publish();

                $response = new stdClass();
                $response->id = (string)$newCollectionFolder->getId();
                $response->httpStatus = 201;
                return $response;
            } else {
                throw new dam_exceptions_BadRequest("A " . $type . " with same title already exist in this level");
            }
        }
        catch (Exception $e) {
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }


    }
}
