<?php
use Ramsey\Uuid\Uuid;

class dam_rest_controllers_Media_Exists extends pinax_rest_core_CommandRest
{
    public function execute($instance, $md5)
    {
        $byteStreamName = __Request::get('bytestream', 'original');
        try {
            if ($instance && $md5) {

                $byteStreamProxy = __ObjectFactory::createObject('dam.models.ByteStreamProxy', $instance);
                $result = $byteStreamProxy->existsByMd5($md5, $byteStreamName);
                return array('ids' => $result);
            } else {
                throw new dam_exceptions_BadRequest("Missing instance parameter");
            }
        }
        catch(Exception $e){
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }
    }
}
