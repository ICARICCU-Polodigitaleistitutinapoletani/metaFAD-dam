<?php

class dam_rest_models_vo_ContainerDetailVO{

    public $id;
    public $MainData;
    public $collection;
    public $folder;
    public $ContainedMedia;
    public $bytestream;

    private function buildNotFoundMessage($entityName, $id)
    {
        return "During $entityName search in DB: unable to load any $entityName with ID = $id";
    }

    public function __construct($container, $options){
        $this->id = (string)$container->uuid;
        $this->datastream = new stdClass();
        $document = pinax_objectFactory::createObject('pinax.dataAccessDoctrine.ActiveRecordDocument');
        // Getting mainData and datastream values
        if(isset($options["MainData"]) && $options["MainData"] == true){
            foreach($container->datastream as $datastreamId){
                if($document->load($datastreamId)){
                    $modelName = explode(".", $document->getType())[2];
                    $this->related_media =$modelName;
                    if($modelName == "MainData" && isset($options["MainData"])){
                        $this->MainData = json_decode($document->document_detail_object);
                        $this->MainData->thumbnail = __Config::get('dam.url').$this->MainData->instance.'/get/' . (string)$container->uuid . '/thumbnail';
                        unset($this->MainData->media_id);
                        unset($this->MainData->instance);
                        $this->MainData->type = $container->media_type;
                        $this->MainData->id = (string)$document->getId();
                    }
                }
                else{
                    throw new dam_exceptions_InternalServerError($this->buildNotFoundMessage("MainData", $datastreamId));
                }
            }
        }


        if(isset($options["collection"]) && $options["collection"] == true){
            $collection = __ObjectFactory::createModel("dam.models.CollectionFolder");
            $treePathHelper = __ObjectFactory::createObject("dam.helpers.TreePath");
            if($container->collection && is_array($container->collection)){
                $this->collection = array();
                foreach($container->collection as $collectionId){
                    if($collection->load($collectionId)){
                        $this->collection[] = $treePathHelper->getIdPath($collection);
                    }
                    else{
                        throw new dam_exceptions_InternalServerError($this->buildNotFoundMessage("Collection from container", $collectionId));
                    }
                }
            }
        }

        if(isset($options["folder"]) && $options["folder"] == true) {
            $folder = __ObjectFactory::createModel("dam.models.CollectionFolder");
            $treePathHelper = __ObjectFactory::createObject("dam.helpers.TreePath");
            if($container->folder){
                if($folder->load($container->folder)){
                    $this->folder = $treePathHelper->getIdPath($folder);
                }
                else{
                    return new dam_exceptions_InternalServerError($this->buildNotFoundMessage("Folder from container", $container->folder));
                }
            }

        }

        if(isset($options["history"]) && $options["history"] == true){
            $allConnectedStream = array_merge($container->datastream, $container->bytestream);
            $this->history = array();
            foreach($allConnectedStream as $streamId){
                if($document->load($streamId)){
                    $modelName = explode(".", $document->getType())[2];
                    if($options["history"] == $modelName) {
                        $historyIterator = pinax_objectFactory::createModelIterator("dam.models.Media");
                        $historyIterator->load('showHistory', array('id' => $document->getId(), 'type' => $document->getType()));
                        foreach ($historyIterator as $historyItem) {
                            $this->history[] = dam_rest_models_vo_HistoryVO::createFromModel($historyItem);
                        }
                    }
                }
                else{
                    return new dam_exceptions_InternalServerError($this->buildNotFoundMessage("History", $streamId));
                }

            }
        }
        if(isset($options["RelatedMedia"]) && $options["RelatedMedia"] == true){
            $solrService = __ObjectFactory::createObject('dam.helpers.SolrService', $container->instance);
            $query = "media_parent_ss:" . $container->uuid;
            $query .= ' AND instance_s:"' . $container->instance.'"';
            $query .= " AND NOT title_collectionFolder_s:*";
            $query .= " AND NOT is_contained_i:1";
            // QUICKFIX aggiunto 100000000, ma va implementata la paginazione lato FE
            $solrResult = $solrService->search(1, $query, null, 'file_name_s ASC', 100000000);
            $resultVO = __ObjectFactory::createObject('dam.rest.models.vo.SearchResultVO');
            $resultVO->getResultsFromSolr($solrResult, null, null);
            $this->RelatedMedia = $resultVO->results;
        }

        if(isset($options["ContainedMedia"]) && $options["ContainedMedia"] == true){
            $solrService = __ObjectFactory::createObject('dam.helpers.SolrService', $container->instance);
            $query = "media_parent_ss:" . $container->uuid;
            $query .= ' AND instance_s:"' . $container->instance.'"';
            $query .= " AND NOT title_collectionFolder_s:*";
            $query .= " AND is_contained_i:1";
            // QUICKFIX aggiunto 100000000, ma va implementata la paginazione lato FE
            $solrResult = $solrService->search(1, $query, null, 'file_name_s ASC', 100000000);
            $resultVO = __ObjectFactory::createObject('dam.rest.models.vo.SearchResultVO');
            $resultVO->getResultsFromSolr($solrResult, null, null);
            $this->ContainedMedia = $resultVO->results;
        }

        if(isset($options["bytestream"]) && $options["bytestream"] == true){
            $this->bytestream = array();
            foreach($container->bytestream as $bytestreamId){
                if($document->load($bytestreamId)){
                    $bytestreamData = json_decode($document->document_detail_object);
                    $bytestreamItem = new stdClass();
                    $bytestreamItem->id = $document->getId();
                    $bytestreamItem->name = $bytestreamData->name;
                    $bytestreamItem->url = __Config::get('dam.url').$bytestreamData->instance.'/get/' . $container->uuid . '/' . $bytestreamData->name;
                    $this->bytestream[] = $bytestreamItem;
                }
                else{
                    throw new dam_exceptions_InternalServerError($this->buildNotFoundMessage("Bytestream", $bytestreamId));
                }
            }
        }

    }

}
