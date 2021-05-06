<?php
class dam_instance_models_proxy_DataStreamProxy extends PinaxObject
{
    protected $dataStreamAr;
    protected $associatedTo;
    protected $schema;

    public function __construct($schemaName)
    {
        $this->dataStreamAr = __ObjectFactory::createModel('dam.instance.models.DataStream');
        $this->loadSchema($schemaName);
    }

    protected function loadSchema($schemaName)
    {
        // TODO
        $schemaFormPath = __Paths::get('APPLICATION_CLASSES').__Config::get('dam.schema.path');
        $this->schema = json_decode(file_get_contents($schemaFormPath . '/' . $schemaName . '.schema.json'));

        $this->dataStreamAr->setTableName('dam.models.'.$schemaName);
        $this->dataStreamAr->setType('dam.models.'.$schemaName);
        $this->associatedTo = $this->schema->associatedTo;

        foreach ($this->schema->properties as $key => $property) {
            if (!$property->meta) {
                continue;
            } else {
                $meta = $property->meta;
            }

            $this->dataStreamAr->addField(new pinax_dataAccessDoctrine_DbField(
                $key,
                $meta->modelType,
                255,
                false,
                null,
                '',
                false,
                false,
                '',
                $meta->searchable ? pinax_dataAccessDoctrine_DbField::INDEXED : pinax_dataAccessDoctrine_DbField::NOT_INDEXED,
                ''
                )
            );
        }
    }

    public function load($id)
    {
        return $this->dataStreamAr->load($id);
    }

    protected function setDataStreamAr($instance, $fkId, $data = array())
    {
        $this->dataStreamAr->instance = $instance;
        $this->dataStreamAr->fk_id = $fkId;

        foreach ($data as $key => $value) {
            $this->dataStreamAr->$key = $value;
        }
    }

    public function save($instance, $fkId, $data = array(), $comment = '')
    {
        $this->setDataStreamAr($instance, $fkId, $data);
        return $this->dataStreamAr->save(null, $comment);
    }

    public function publish($instance, $fkId, $data = array(), $comment = '')
    {
        $this->setDataStreamAr($instance, $fkId, $data);
        return $this->dataStreamAr->publish(null, $comment);
    }

    public function delete()
    {
        return $this->dataStreamAr->delete();
    }

    public function saveCurrentPublished($instance, $fkId, $data = array(), $comment = '')
    {
        $this->setDataStreamAr($instance, $fkId, $data);
        return $this->dataStreamAr->saveCurrentPublished($comment);
    }

    public function getDataStreamVO($statusCode = 200, $fallbackPath = null)
    {
        if ($fallbackPath) {
             $this->applyFallback($fallbackPath);
        }

        return __ObjectFactory::createObject('dam.instance.models.vo.DataStreamVO', $this->dataStreamAr, $statusCode);
    }

    public function getAr()
    {
        return $this->dataStreamAr;
    }

    public function isAssociatedToBytestream()
    {
        return $this->associatedTo == 'bytestream';
    }

    public function rollback($oldId)
    {
        $it = $this->dataStreamAr->createRecordIterator();
        $it->where('document_detail_id', $oldId)->allStatuses();
        $ar = $it->first();
        $data = json_decode($ar->document_detail_object);
        try {
            $this->publish($data->instance, $data->fk_id, $data, 'Ripristino versione del ' . $ar->document_detail_modificationDate);
            return $this->getDataStreamVO();
        } catch (pinax_validators_ValidationException $e) {
            return $e->getErrors();
        }
    }

    public function getHistory($dataStreamId)
    {
        $historyIterator = pinax_objectFactory::createModelIterator("dam.models.Media");
        $historyIterator->load('showHistory', array('id' => $dataStreamId, 'type' => $this->dataStreamAr->getType()));

        $history = array();

        foreach ($historyIterator as $historyItem) {
            $history[] = dam_rest_models_vo_HistoryVO::createFromModel($historyItem);
        }

        return $history;
    }

    /**
     * @param  string $fallbackPath
     * @return void
     */
    private function applyFallback($fallbackPath)
    {
        $fallbackMap = [];

        foreach ($this->schema->properties as $key => $property) {
            if (!$property->meta || !$property->meta->fallback) {
                continue;
            }

            if (!isset($fallbackMap[$property->meta->fallback->type])) {
                $fallbackMap[$property->meta->fallback->type] = __ObjectFactory::createObject($property->meta->fallback->type, $fallbackPath);
            }

            $fallbackClass = $fallbackMap[$property->meta->fallback->type];
            try {
                $this->dataStreamAr->{$key} = $fallbackClass->apply($property->meta->fallback->command, $this->dataStreamAr->{$key}, $this->dataStreamAr);
            } catch (Exception $e) {}
        }
    }
}
