<?php

class dam_rest_models_vo_SearchResultVO
{
    public $numFound;
    public $page;
    public $pages;
    public $filters_applied;
    public $filters;
    public $results;

    function getResultsFromSolr($solrResponse, $queryParams, $facetParams, $filtersOR = null, $rowsPerPage = null)
    {
        $application = pinax_ObjectValues::get('org.pinax', 'application');
        $schemaManagerService = $application->retrieveProxy('dam.services.SchemaManagerService');

        $infos = __ObjectFactory::createObject("dam.rest.models.vo.InformationVO");
        $orFacets = array_map(array($schemaManagerService, "translateToSolrKey"), $infos->facetsOR);

        $resultsForPage = $rowsPerPage ? $rowsPerPage : __Config::get('dam.solr.rowsPerPage');
        $this->numFound = $solrResponse->response->numFound;
        $this->page = ($solrResponse->response->start / $resultsForPage) + 1;
        $this->pages = ceil($solrResponse->response->numFound / $resultsForPage);
        $this->results = Array();
        if ($solrResponse->response && $solrResponse->response->docs) {
            foreach ($solrResponse->response->docs as $solrDocument) {
                $singleMediaVO = __ObjectFactory::createObject('dam.rest.models.vo.SingleMediaVO');
                $singleMediaVO->createFromSolrDocument($solrDocument);
                $this->results[] = $singleMediaVO;
            }
        }
        $this->filters = new stdClass();
        if ($solrResponse->facet_counts && $solrResponse->facet_counts->facet_fields) {
            foreach ($solrResponse->facet_counts->facet_fields as $solrFacetKey => $solrFacetValue) {
                if (count($solrFacetValue) === 0 && !in_array($solrFacetKey, $orFacets)) {
                    continue;
                }
                $transaltedKey = $schemaManagerService->translateFromSolrKey($solrFacetKey);
                $this->filters->{$transaltedKey} = array();
                if (is_array($solrFacetValue)) {
                    $index = 0;
                    $solrFacet = new stdClass();
                    foreach ($solrFacetValue as $arrayItem) {
                        if ($index % 2 == 0) {
                            $solrFacet->label = $arrayItem;
                        } else if ($arrayItem != 0 || in_array($solrFacetKey, $orFacets)) {
                            $solrFacet->value = $arrayItem;
                            $facetVO = __ObjectFactory::createObject('dam.rest.models.vo.FacetVO');
                            $facetVO->getFacetFromSolr($solrFacet);
                            $this->filters->{$transaltedKey}[] = $facetVO;
                        }
                        $index++;
                    }

                }
            }
        }
        $this->filters_applied = new stdClass();
        $this->filters_applied->search = $queryParams;
        $this->filters_applied->filters = $facetParams;
        $this->filters_applied->filtersOR = $filtersOR;
    }
}
