<?php

class dam_rest_controllers_Main_Rollback extends pinax_rest_core_CommandRest
{
    function execute($instance, $modelName)
    {
        try {
            if ($instance) {
                $data = json_decode(__Request::get('__postBody__'));
                $id = $data->id;
                $detailId = $data->detailId;
                $dataStreamProxy = __ObjectFactory::createObject('dam.instance.models.proxy.DataStreamProxy', $modelName);
                if ($dataStreamProxy->load($id)) {
                    return $dataStreamProxy->rollback($detailId);
                } else {
                    throw new dam_exceptions_BadRequest("The resource doesn't exist");
                }
            } else {
                throw new dam_exceptions_BadRequest("Missing instance parameter");
            }
        } catch (Exception $e) {
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }
    }

}
