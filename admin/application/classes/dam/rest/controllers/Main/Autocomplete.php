<?php

class dam_rest_controllers_Main_Autocomplete extends pinax_rest_core_CommandRest
{

     function execute($instance){
        try{
            if($instance){
                $data = json_decode(__Request::get('__postBody__'));
                if($data->field && $data->value){
                    $solrField = $data->field;
                    if($solrField != null){
                        $solrService = __ObjectFactory::createObject('dam.helpers.SolrService');
                        $solrResponse = $solrService->autocomplete($instance, $solrField, $data->value);
                        $valueArray = array();
                        if ($solrResponse->facet_counts && $solrResponse->facet_counts->facet_fields) {
                            $facets = $solrResponse->facet_counts->facet_fields->{$solrField};
                            $numfacets = count($facets);
                            for ($i=0; $i<$numfacets; $i++) {
                                if ($i % 2 === 0) {
                                    $valueArray[] = $facets[$i];
                                }
                            }
                            $response = __ObjectFactory::createObject("dam.rest.models.vo.AutocompleteVO", $data->field, $valueArray);
                            return $response;
                        }
                        else{

                             return new dam_exceptions_InternalServerError("Error in query");
                        }
                    }
                    else{
                        throw new dam_exceptions_BadRequest("Field doesn't exist");
                    }

                }
                else{
                    throw new dam_exceptions_BadRequest("Missing parameter");
                }

            }
            else{
                throw new dam_exceptions_BadRequest;
            }
        }
        catch(Exception $e){
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }

    }

}
