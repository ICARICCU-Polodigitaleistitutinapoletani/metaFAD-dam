<?php

class dam_rest_models_vo_MediaDetailVO{

    public $id;
    public $MainData;
    public $datastream;
    public $bytestream;
    public $history;
    public $collection;
    public $folder;
    public $RelatedMedia;

    private function buildNotFoundMessage($entityName, $id)
    {
        return "During $entityName search in DB: unable to load any $entityName with ID = $id";
    }

    public function __construct($media, $options)
    {
        $this->id = (string)$media->uuid;
        $this->datastream = new stdClass();
        // Getting mainData and datastream values
        if(isset($options["MainData"]) || isset($options["datastream"])){
            foreach($media->datastream as $datastreamId){
                $document = pinax_objectFactory::createObject('pinax.dataAccessDoctrine.ActiveRecordDocument');
                if($document->load($datastreamId)){
                    $modelName = explode(".", $document->getType())[2];
                    $this->related_media =$modelName;
                    if($modelName == "MainData" && isset($options["MainData"])){
                        $this->MainData = json_decode($document->document_detail_object);
                        unset($this->MainData->media_id);
                        unset($this->MainData->instance);
                        $this->MainData->type = $media->media_type;
                        $this->MainData->id = (string)$document->getId();
                    }
                    else if(isset($options["datastream"]) && ($options["datastream"] == $modelName || $options["datastream"]=='all')) {
                        $this->datastream->{$modelName} = json_decode($document->document_detail_object);
                        unset($this->datastream->{$modelName}->media_id);
                        unset($this->datastream->{$modelName}->instance);
                        $this->datastream->{$modelName}->id = (string)$document->getId();
                    }
                }
                else{
                    throw new dam_exceptions_InternalServerError($this->buildNotFoundMessage("MainData", $datastreamId));
                }
            }
        }

        if(isset($options["bytestream"])){
            $options["bytestream"] = $options["bytestream"]=='true' ? 'all' : $options["bytestream"];
            $this->bytestream = array();
            foreach($media->bytestream as $bytestreamId){
                $document = pinax_objectFactory::createObject('pinax.dataAccessDoctrine.ActiveRecordDocument');
                if($document->load($bytestreamId)){
                    $bytestreamData = json_decode($document->document_detail_object);
                    if ($bytestreamData->name!=$options["bytestream"] && $options["bytestream"]!='all') {
                        continue;
                    }
                    $date = DateTime::createFromFormat('d/m/Y H:i:s', $document->document_creationDate);
                    $bytestreamItem = new stdClass();
                    $bytestreamItem->id = $document->getId();
                    $bytestreamItem->name = $bytestreamData->name;
                    $bytestreamItem->size = $bytestreamData->size;

                    // NOTE: gli url devono essere costruiti con il routing!
                    $bytestreamItem->url = __Config::get('dam.url').$bytestreamData->instance.'/get/'. $media->uuid . '/' . $bytestreamData->name;
                    $bytestreamItem->uri = $date->format('Y-m-d') . '/' . $media->uuid . '/' . $bytestreamData->filename;
                    $bytestreamItem->urlStream =  __Config::get('dam.streamURL') . $media->uuid . '/' . $bytestreamData->name;
                    $this->bytestream[] = $bytestreamItem;
                }
                else{
                    throw new dam_exceptions_InternalServerError($this->buildNotFoundMessage("Bytestream", $bytestreamId));
                }
            }
        }

        if(isset($options["collection"]) && $options["collection"] == true){
            $treePathHelper = __ObjectFactory::createObject("dam.helpers.TreePath");
            if($media->collection && is_array($media->collection)){
                $this->collection = array();
                foreach($media->collection as $collectionId){
                    $collection = __ObjectFactory::createModel("dam.models.CollectionFolder");
                    if($collection->load($collectionId)){
                        $this->collection[] = $treePathHelper->getIdPath($collection);
                    }
                    else{
                        throw new dam_exceptions_InternalServerError($this->buildNotFoundMessage("Collection from Media", $collectionId));
                    }
                }
            }
        }

        if(isset($options["folder"]) && $options["folder"] == true) {
            $folder = __ObjectFactory::createModel("dam.models.CollectionFolder");
            $treePathHelper = __ObjectFactory::createObject("dam.helpers.TreePath");
            if($media->folder){
                if($folder->load($media->folder)){
                    $this->folder = $treePathHelper->getIdPath($folder);
                }
                else{
                    return new dam_exceptions_InternalServerError($this->buildNotFoundMessage("Folder from Media", $media->folder));
                }
            }

        }

        if(isset($options["history"]) && $options["history"] == true){
            $allConnectedStream = array_merge($media->datastream, $media->bytestream);
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
            $solrService = __ObjectFactory::createObject('dam.helpers.SolrService', $media->instance);
            $query = "media_parent_ss:" . $media->uuid;
            $query .= ' AND instance_s:"' . $media->instance.'"';
            $query .= " AND NOT title_collectionFolder_s:*";
            $query .= " AND NOT is_contained_i:1";
            // QUICKFIX aggiunto 100000000, ma va implementata la paginazione lato FE
            $solrResult = $solrService->search(1, $query, null, null, 100000000);
            $resultVO = __ObjectFactory::createObject('dam.rest.models.vo.SearchResultVO');
            $resultVO->getResultsFromSolr($solrResult, null, null);
            $this->RelatedMedia = $resultVO->results;
        }

    }

}
