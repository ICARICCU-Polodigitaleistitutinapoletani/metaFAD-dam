<?php

class dam_rest_controllers_Main_Search extends pinax_rest_core_CommandRest
{
    function execute($instance)
    {
        $data = json_decode(__Request::get('__postBody__'));
        $rowsPerPage = $data->rowsPerPage ? $data->rowsPerPage : null;
        $searchService = __ObjectFactory::createObject('dam.services.SearchService', $instance);

        return $searchService->doSolrSearch($instance, $data->search, $data->filters, $data->filtersOR, $data->page, $data->sort, $rowsPerPage);
    }
}