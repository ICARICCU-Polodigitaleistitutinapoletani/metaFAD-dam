<?php

class dam_rest_models_vo_MediaVO
{
    public $id;
    public $media_type;
    public $related_collection_folder;
    public $media_parent;
    public $media_child;
    public $datastream;
    public $bytestream;

    function __construct($id, $mediaType, $related_collection_folder, $media_parent, $media_child, $datastream, $bytestream)
    {
        $this->id = $id;
        $this->media_type = $mediaType;
        $this->related_collection_folder = $related_collection_folder;
        $this->media_parent = $media_parent;
        $this->media_child = $media_child;
        $this->datastream = $datastream;
        $this->bytestream = $bytestream;
    }

}