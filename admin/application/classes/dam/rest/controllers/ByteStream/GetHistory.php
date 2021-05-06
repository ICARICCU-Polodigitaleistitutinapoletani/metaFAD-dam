<?php
class dam_rest_controllers_ByteStream_GetHistory extends pinax_rest_core_CommandRest
{
    function execute($instance, $mediaId, $byteStreamId, $modelName)
    {
        try {
            $result = array(
                'id' => $mediaId,
                'history' => array()
            );
            $byteStream = __ObjectFactory::createModel('dam.models.ByteStream');
            if ($byteStream->load($byteStreamId)){
                $dataStreamProxy = __ObjectFactory::createObject('dam.instance.models.proxy.DataStreamProxy', $modelName);
                if (isset($byteStream->datastream[$modelName])) {
                    $dataStreamId = $byteStream->datastream[$modelName];
                    $result['history'] = $dataStreamProxy->getHistory($dataStreamId);
                }

                return $result;
            } else {
                throw new dam_exceptions_BadRequest("ByteStream not found");
            }
        } catch(Exception $e){
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }
    }
}