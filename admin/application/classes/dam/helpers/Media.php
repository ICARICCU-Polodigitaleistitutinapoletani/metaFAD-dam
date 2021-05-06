<?php
class dam_helpers_Media extends PinaxObject
{
    public $media;

    public function __construct($media){
        $this->media = $media;
    }

    public function delete(){
        $document = __ObjectFactory::createModel("pinax.dataAccessDoctrine.ActiveRecordDocument");
        $solrService = __ObjectFactory::createObject("dam.helpers.SolrService");
        $solrMapperHelper = __ObjectFactory::createObject("dam.helpers.SolrMapper");

        // Delete related datastream
        if($this->media->datastream && is_array($this->media->datastream)){
            foreach($this->media->datastream as $datastreamId){
                if($document->load($datastreamId)) {
                    $document->delete();
                }
            }
        }

        $this->deleteByteStreams();

        // Delete media ID from collection and folder
        $collectionFolder = __ObjectFactory::createModel("dam.models.CollectionFolder");
        if($this->media->collection && is_array($this->media->collection)) {
            foreach ($this->media->collection as $collectionId) {
                if ($collectionFolder->load($collectionId)) {
                    $arr = $collectionFolder->media_id;
                    unset($arr[array_search((string)$this->media->uuid, $arr)]);
                    $collectionFolder->media_id = array_values($arr);
                    $collectionFolder->publish();
                }
            }
        }
        if($this->media->folder){
            if($collectionFolder->load($this->media->folder)){
                $arr = $collectionFolder->media_id;
                unset($arr[array_search((string)$this->media->uuid, $arr)]);
                $collectionFolder->media_id = array_values($arr);
                $collectionFolder->publish();
            }
        }


        // Delete media ID from related media
        if($this->media->media_child && is_array($this->media->media_child)){
            foreach($this->media->media_child as $childId){
                $relatedMedia = __ObjectFactory::createModel("dam.models.Media");
                if ($relatedMedia->load($childId)){
                    $arr = $relatedMedia->media_parent;
                    unset($arr[array_search($this->media->uuid, $arr)]);
                    $relatedMedia->media_parent = array_values($arr);
                    $relatedMedia->is_contained = false;
                    $relatedMedia->publish();
                    $document = $solrMapperHelper->mapMediaToSolr($relatedMedia);
                    $solrService->publish($document);
                }
            }
        }

        if($this->media->media_parent && is_array($this->media->media_parent)){
            foreach($this->media->media_parent as $parentId){
                $relatedMedia = __ObjectFactory::createModel("dam.models.Media");
                if($relatedMedia->load($parentId)){
                    $arr = $relatedMedia->media_child;
                    unset($arr[array_search($this->media->uuid, $arr)]);
                    $relatedMedia->media_child = array_values($arr);
                    $relatedMedia->publish();
                    $document = $solrMapperHelper->mapMediaToSolr($relatedMedia);
                    $solrService->publish($document);
                }
            }
        }
        $solrService->delete($this->media->uuid);
        $this->media->delete();
    }

    protected function deleteByteStreams()
    {
        // Delete bytestream
        if ($this->media->bytestream && is_array($this->media->bytestream)) {
            $bytestreamHelper = __ObjectFactory::createModel("dam.helpers.ByteStream");
            $bytestreamHelper->deleteAll($this->media->bytestream);
        }
    }
}
