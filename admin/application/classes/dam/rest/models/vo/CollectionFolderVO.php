<?php

class dam_rest_models_vo_CollectionFolderVO
{
    public $id;
    public $key;
    public $title;
    public $folder;
    public $lazy;

    function createFromDocument($collectionFolder){
        $this->id = (string)$collectionFolder->getId();
        $this->key = $this->id;
        $this->title = $collectionFolder->title;
        $this->folder = true;
        $this->lazy = (__ObjectFactory::createModelIterator("dam.models.CollectionFolder")
                    ->where("parent", $this->id)->count()) ? true : false;
    }
}