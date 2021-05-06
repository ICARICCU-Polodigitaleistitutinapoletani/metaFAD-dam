<?php
class dam_services_SchemaManagerService extends PinaxObject
{
    // TODO mettere nel config
    // mappatura comune a tutte le installazioni
    protected $solrMap = array(
        "type" => "media_type_s",
        "file_extension" => "file_type_s",
        "datastream_num" => "number_of_datastream_i",
        "bytestream_num" => "number_of_bytestream_i",
        "date" => "update_at_s",
        "collection"=> "collection_ss",
        "folder" => "folder_s",
        "bytestream_last_update" => "bytestream_last_update_s"
    );

    // TODO mettere nel config
    // TODO localizzare
    protected $solrFacetTranslations = array(
        "IMAGE" => "Immagini",
        "CONTAINER" => "Contenitori",
        "OFFICE" => "Documenti",
        "PDF" => "File PDF",
        "ARCHIVE" => "File compressi",
        "AUDIO" => "Audio",
        "VIDEO" => "Video",
        "OTHER" => "Altri file",
        "collection" => "Collezioni",
        "folder" => "Cartelle",
        "type" => "Tipi file"
    );

    protected $solrModelMap;
    protected $solrFacetFields = array();
    // TODO mettere nel config
    protected $availableSearchParams = array(
        array(
            'autocomplete' => 'folder_s',
            'key' => 'folder',
            'name' => 'Cartella',
            'placeholder' => 'Cartella ...'
        ),
        array(
            'autocomplete' => 'collection_ss',
            'key' => 'collection',
            'name' => 'Collezione',
            'placeholder' => 'Collezione ...'
        )
    );

    protected $exportFields;

    // TODO caching
    public function __construct()
    {
        $schemaFormPath = __Paths::get('APPLICATION_CLASSES').__Config::get('dam.schema.path');
        $files = glob($schemaFormPath.'*.schema.json');

        $this->solrModelMap = json_decode(__Config::get("dam.solr.document"));
        $this->solrFacetFields = explode(',', __Config::get('dam.solr.facetFields'));
        $this->exportFields = json_decode(__Config::get('dam.search.exportFields'));
        if (!$this->exportFields) {
            $this->exportFields = new StdClass;
        }

        foreach ($files as $file) {
            $schemaName = basename($file);
            $schemaName = substr($schemaName, 0, strpos($schemaName, '.'));

            // ignora SimpleData perchè i campi sono già definiti in altri datastream
            if ($schemaName === 'SimpleData') {
                continue;
            }

            $schema = json_decode(file_get_contents($file));
            if (!$schema) {
                throw new Exception('Error loading schema: '.$schemaName. ' - '. json_last_error_msg());
            }
            $this->parseSchema($schemaName, $schema);
        }
    }

    protected function parseSchema($schemaName, $schema)
    {
        foreach ($schema->properties as $fieldName => $property) {
            if (!property_exists($property, 'meta')) {
                continue;
            }

            $meta = $property->meta;
            if (property_exists($meta, 'solrField')) {
                $this->solrMap[$fieldName] = $meta->solrField;
                $this->solrModelMap->{$meta->solrField} = 'dam.models.'.$schemaName.':'.$fieldName;

                if (property_exists($meta, 'searchable')) {
                     $this->availableSearchParams[] = array(
                        'autocomplete' => $meta->solrField,
                        'key' => $fieldName,
                        'name' => $property->title,
                        'placeholder' => $property->title.' ...'
                    );
                }
            }

            if (property_exists($meta, 'facet') && $meta->facet) {
                $this->solrFacetFields[] = $meta->solrField;
                $this->solrFacetTranslations[$fieldName] = $property->title;
            }

            if (property_exists($meta, 'exportField') && $meta->exportField) {
                $this->exportFields->$fieldName = $meta->solrField;
            }
        }
    }

    public function translateToSolrKey($key){
        return $this->solrMap[$key] ? $this->solrMap[$key] : $key;
    }

    public function translateFromSolrKey($key){
        return array_search($key, $this->solrMap);
    }

    public function getSolrModelMap()
    {
        return clone $this->solrModelMap;
    }

    public function getSolrFacetFields()
    {
        return $this->solrFacetFields;
    }

    public function getSolrFacetTranslations()
    {
        return $this->solrFacetTranslations;
    }

    public function getAvailableSearchParams()
    {
        return $this->availableSearchParams;
    }

    public function getExportFields()
    {
        return $this->exportFields;
    }

    function onRegister()
    {
    }
}