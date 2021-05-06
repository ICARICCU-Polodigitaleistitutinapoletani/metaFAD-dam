<?php
class dam_rest_controllers_ByteStream_PutByteStream extends pinax_rest_core_CommandRest
{
    private $instance;
    private $mediaId;
    private $byteStreamId;
    private $modelName;
    private $byteStreamModel;
    private $data;
    /** @var dam_helpers_ByteStream $bytestreamHelper */
    private $byteStreamHelper;
    /** @var dam_models_ByteStreamProxy $bytestreamProxy */
    private $byteStreamProxy;

    function execute($instance, $mediaId, $byteStreamId, $modelName, $data)
    {
        try {
            $this->instance = $instance;
            $this->mediaId = $mediaId;
            $this->byteStreamId = $byteStreamId;
            $this->modelName = $modelName;
            $this->data = $data;
            $this->byteStreamModel = __ObjectFactory::createModel('dam.models.ByteStream');
            $this->byteStreamHelper = __ObjectFactory::createModel("dam.helpers.ByteStream");
            $this->byteStreamProxy = __ObjectFactory::createModel("dam.models.ByteStreamProxy");
            $this->byteStreamModel->load($byteStreamId) ? $this->processByteStream() : $this->throwBadRequest('ByteStream not found');
            return null;
        } catch(Exception $e) {
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }
    }

    private function processByteStream()
    {
        // aggiorna il bytestream su db
        $this->byteStreamProxy->modify($this->byteStreamId, $this->data);
        if ($this->byteStreamHelper->existsBytestream($this->data->bytestream)) {
            $this->byteStreamHelper->addBytestream($this->instance, $this->data->path, $this->mediaId, false);
        }
    }

    private function throwBadRequest($message)
    {
        throw new dam_exceptions_BadRequest($message);
    }
}