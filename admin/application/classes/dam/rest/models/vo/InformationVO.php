<?php

class dam_rest_models_vo_InformationVO extends PinaxObject
{
    public $availableSearchParams;
    public $advancedSearchFixed;
    public $disabledFeatures = array();
    public $enabledFeatures = array();
    public $fileTypes;
    public $filtersLanguage;
    public $facetsOR;
    public $uploadMaxFiles;
    public $uploadMaxSize;
    public $rowPerPage;
    public $rowsPerPage;
    public $schemaForm;

    public function __construct()
    {
        $this->rowsPerPage = __Config::get('dam.solr.rowsPerPage');
        $this->fileTypes = $this->fileTypes();
        $this->disabledFeatures = $this->extractCommaSeparated(__Config::get('dam.disabledFeatures'));
        $this->enabledFeatures = $this->extractCommaSeparated(__Config::get('dam.enabledFeatures'));
        $this->facetsOR = $this->extractCommaSeparated(__Config::get("dam.solr.facetsOR"));

        $this->filtersLanguage = new stdClass();
        $application = pinax_ObjectValues::get('org.pinax', 'application');
        $schemaManagerService = $application->retrieveProxy('dam.services.SchemaManagerService');
        $this->availableSearchParams = $schemaManagerService->getAvailableSearchParams();
        $this->advancedSearchFixed = __Config::get('dam.advancedSearchFixed');
        $uploadMaxFiles = __Config::get('dam.uploadMaxFiles');
        $uploadMaxSize = __Config::get('dam.uploadMaxSize');
        $this->uploadMaxFiles = $uploadMaxFiles!=='' ? $uploadMaxFiles : null;
        $this->uploadMaxSize = $uploadMaxSize!=='' ? $uploadMaxSize : null;

        $translation = $schemaManagerService->getSolrFacetTranslations();
        foreach ($translation as $key => $value) {
            $this->filtersLanguage->{$key} = $value;
        }
        $this->schemaForm = new stdClass();
        $this->schemaForm->MainData = $this->getSchemaForm("MainData");
        $this->schemaForm->datastream = new stdClass();
        $types = json_decode(__Config::get('dam.types'));

        foreach ($types as $key => $value) {
            $this->schemaForm->datastream->{$key} = array();
            if (!$value) continue;
            foreach ($value as $schemaFormName) {
                $this->schemaForm->datastream->{$key}[$schemaFormName] = $this->getSchemaForm($schemaFormName);
            }
        }
    }

    /**
     * Estrae i valori presenti nelle stringhe della forma "A1<separator>...<separator>An". Ai saranno i valori estratti.
     * È possibile pure fare il trim dei valori estratti e configurare il separatore, che di default è ",".
     * @param string $stringSource Sorgente da cui estrarre i valori
     * @param bool $shouldTrim Se applicare il trim a tutti i valori
     * @param string $comma Separatore da riconoscere
     * @return array Valori estratti
     */
    private function extractCommaSeparated($stringSource, $shouldTrim = true, $comma = ",")
    {
        return
            array_filter(
                array_map(
                    function ($a) use ($shouldTrim) {
                        return $shouldTrim ? trim($a) : $a;
                    },
                    explode($comma, $stringSource ?: "")
                )
            );
    }

    private function getSchemaForm($name)
    {
        $schemaFormPath = __Paths::get('APPLICATION_CLASSES') . __Config::get('dam.schema.path');
        $obj = new stdClass();
        $obj->schema = json_decode(file_get_contents($schemaFormPath . '/' . $name . '.schema.json'));
        $obj->form = json_decode(file_get_contents($schemaFormPath . '/' . $name . '.form.json'));
        return $obj;
    }


    private function fileTypes()
    {
        $typeOfChange = json_decode(__Config::get('dam.typeOfChange'));
        $result = array();

        foreach ($typeOfChange as $key => $value) {
            if (!property_exists($value, 'conversione')) continue;
            $result[$key] = $value->conversione;
        }

        return $result;
    }

}
