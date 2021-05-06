<?php

abstract class dam_models_AbstractDocumentProxy extends PinaxObject
{

    protected $instance;
    protected $application;
    protected $user;

    function __construct($instance = null)
    {
        $this->instance = $instance;
        $this->application = pinax_ObjectValues::get('org.pinax', 'application');
        $this->user = $this->application->getCurrentuser();
    }

    public function getInfo()
    {
        return json_decode(__Config::get('dam.types'));
    }

    public function add($data, $comment = '', $mediaId = null, $publish = true)
    {
        return $this->modify(null, $data, $comment, $mediaId, $publish);
    }

    abstract public function modify($id, $data, $comment = '', $mediaId = null, $publish = true, $forceNew = false);

    //abstract public function validate($data);

    public function rollback($oldId, $model)
    {
        $it = pinax_objectFactory::createModelIterator($model);
        $it->where("document_detail_id", $oldId)->allStatuses();
        $ar = $it->first();
        $id = $ar->document_detail_FK_document_id;

        $oldData = json_decode($ar->document_detail_object);
        $currentDocument = pinax_objectFactory::createObject('pinax.dataAccessDoctrine.ActiveRecordDocument');
        if (!$oldData || !$currentDocument->load($id)) {
            return array('http-status' => 400);
        }

        if ($oldData->media_id) {
            $mediaId = $oldData->media_id;
        }
        $newData = json_decode($currentDocument->getRawData()->document_detail_object);
        foreach ($newData as $k => $v) {
            $newData->{$k} = "";
        }
        foreach ($oldData as $k => $v) {
            $newData->{$k} = $v;
        }

        return $this->modify($id, $newData, 'Ripristino versione del ' . $ar->document_detail_modificationDate, $mediaId);
    }

    protected function createModel($id = null, $model)
    {
        // NOTA: questo metodo  sbagiato perché non gestice il caso in cui
        // il documento non viene caricato perché l'id è sbagliato
        $document = pinax_objectFactory::createModel($model);
        if ($id) {
            $document->load($id);
        }
        return $document;
    }

    /**
     * Restituisce l'istanza del model
     *
     * DU: ho riscritto il metodo perché createModel ha problemi
     *
     * @param  string $instance Nome dell'istanza del DAM
     * @param  string $model    Nome del model
     * @param  string $id       id del model da caricare
     * @return pinax_dataAccessDoctrine_ActiveRecordDocument
     */
    protected function createModelNew($instance, $model, $id=null)
    {
        // NOTA: questo metodo  sbagiato perché non gestice il caso in cui
        // il documento non viene caricato perché l'id è sbagliato
        $document = pinax_objectFactory::createModel($model);
        if ($id) {
            if (!$document->load($id)) {
                throw dam_exceptions_MediaException::notFound($id);
            }

            if ( $document->instance != $instance) {
                throw dam_exceptions_MediaException::wrongInstance($id);
            }
        }

        return $document;
    }
}
