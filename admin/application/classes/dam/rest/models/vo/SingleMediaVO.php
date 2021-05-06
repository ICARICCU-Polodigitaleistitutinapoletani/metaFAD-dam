<?php
class dam_rest_models_vo_SingleMediaVO
{
    public $id;
    public $title;
    public $type;
    public $file_extension;
    public $thumbnail;
    public $datastream_num;
    public $bytestream_num;
    public $date;
    public $collection;
    public $folder;
    public $RelatedMedia;
    public $bytestream_batch;
    public $file_uri;
    public $exportFields;
    public $size;

    function createFromSolrDocument($solrDocument)
    {
        $this->id = $solrDocument->id;
        $this->title = $solrDocument->title_s_lower;
        $this->type = $solrDocument->media_type_s;
        $this->file_extension = $solrDocument->file_type_s;
        $this->datastream_num = $solrDocument->number_of_datastream_i;
        $this->bytestream_num = $solrDocument->number_of_bytestream_i;
        $this->date = $solrDocument->update_at_s;
        // TODO: verificare il path
        $this->thumbnail = __Config::get('dam.url').$solrDocument->instance_s.'/get/' . $this->id . '/thumbnail';
        $this->url = __Config::get('dam.url').$solrDocument->instance_s.'/get/' . $this->id . '/original';

        if($solrDocument->bytestream_last_update_s){
            $this->thumbnail.= '?timestamp=' . $solrDocument->bytestream_last_update_s ;
        }
        $this->collection = $solrDocument->collection_ss;
        $this->folder = $solrDocument->folder_s;
        $this->bytestream_batch = ($solrDocument->bytestream_batch_s) ? true : false;
        $this->RelatedMedia = false;
        $this->file_uri = $solrDocument->file_title_s;
        $this->original_file_name = $solrDocument->original_file_name_s;
        $this->size = $solrDocument->size_i;

        // ottimizzata gestione ContainedMedia
        // TODO i media collegati non devono finire in media_child_ss
        if ($solrDocument->media_child_ss) {
            $this->ContainedMedia = true;
        }

        $application = pinax_ObjectValues::get('org.pinax', 'application' );
        $schemaManagerService = $application->retrieveProxy('dam.services.SchemaManagerService');
        $exporFields = $schemaManagerService->getExportFields();
        $this->exportFields = array();
        foreach ($exporFields as $key => $solrField) {
            $this->exportFields[$key] = $solrDocument->$solrField;
        }
    }
}
