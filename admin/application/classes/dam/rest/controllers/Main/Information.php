<?php

class dam_rest_controllers_Main_Information extends pinax_rest_core_CommandRest
{
    function execute($instance)
    {
        try {
            if (!$instance) {
                throw new dam_exceptions_BadRequest("Instance not found in this installation. Requested instance value: " . ($instance ?: "<unspecified>"));
            }

            return __ObjectFactory::createObject("dam.rest.models.vo.InformationVO");
        } catch (Exception $e) {
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }
    }
}
