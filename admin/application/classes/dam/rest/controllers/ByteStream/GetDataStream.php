<?php
class dam_rest_controllers_ByteStream_GetDataStream extends pinax_rest_core_CommandRest
{
    function execute($instance, $mediaId, $byteStreamId, $modelName)
    {
        try {
            $byteStream = __ObjectFactory::createModel('dam.models.ByteStream');

            if ($byteStream->load($byteStreamId)){
                $bytestreamProxy = __ObjectFactory::createObject('dam.models.ByteStreamProxy');
                $filePath = $bytestreamProxy->streamPath($byteStream);

                $dataStreamProxy = __ObjectFactory::createObject('dam.instance.models.proxy.DataStreamProxy', $modelName);
                if (isset($byteStream->datastream[$modelName])) {
                    $dataStreamId = $byteStream->datastream[$modelName];
                    $dataStreamProxy->load($dataStreamId);
                }

                return array($modelName => $dataStreamProxy->getDataStreamVO(null, $filePath));
            } else {
                throw new dam_exceptions_BadRequest("ByteStream not found");
            }
        } catch(Exception $e){
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }
    }
}
