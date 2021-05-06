<?php
class dam_rest_controllers_ByteStream_GetAllTypes extends pinax_rest_core_CommandRest
{
    function execute($instance)
    {
        try {
            $it = __ObjectFactory::createModelIterator('dam.models.ByteStreamType')
                ->where('bytestream_type_instance', $instance);
            $types = array();
            foreach ($it as $ar) {
                $types[] = $ar->bytestream_type_name;
            }
            return $types;
        } catch(Exception $e){
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }
    }
}