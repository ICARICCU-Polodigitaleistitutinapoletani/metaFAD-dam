<?php

class dam_services_SearchService extends PinaxObject
{
    protected $application;
    protected $solrService;

    function __construct($instance)
    {
        $this->application = pinax_ObjectValues::get('org.pinax', 'application');
        $this->solrService = __ObjectFactory::createObject('dam.helpers.SolrService', $instance);
    }

    public function doSolrSearch($instance, $queryParams, $facetParams = null, $filtersOR = null, $page = 1, $sortParams = null, $resultsForPage = null, $facetEnabled = true)
    {
        try {
            $solrQuery = $this->createSolrQuery($queryParams, $filtersOR, $instance);
            $solrSort = $this->createSolrSort($sortParams);
            $solrFacet = $this->createSolrFacets($facetParams);

            if (!$page || $page < 1) {
                $page = 1;
            }
            $solrResult = $this->solrService->search($page, $solrQuery, $solrFacet, $solrSort, $resultsForPage, $facetEnabled);
            $resultVO = __ObjectFactory::createObject('dam.rest.models.vo.SearchResultVO');
            $resultVO->getResultsFromSolr($solrResult, $queryParams, $facetParams, $filtersOR, $resultsForPage);
        } catch (Exception $e) {
            return array('http-status' => '500', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'traceString' => $e->getTraceAsString());
        }

        return $resultVO;
    }

    public function createSolrQuery($queryParams, $filtersOR, $instance)
    {
        $q = array();
        $qOR = array();

        $schemaManagerService = $this->application->retrieveProxy('dam.services.SchemaManagerService');

        if ($queryParams) {
            foreach ($queryParams as $param) {
                $solrKey = $schemaManagerService->translateToSolrKey(key($param));
                $value = str_replace(' ', '\ ', $param->{key($param)});
                $not = '';
                if ($value{0}==='-') {
                    $value = substr($value, 1);
                    $not = 'NOT ';
                }
                if ($solrKey == "text") {
                    $q[] = $value;
                } else if ($solrKey) {
                    $q[] = $not.$solrKey.':' .$value;
                } else {
                    throw new dam_exceptions_InternalServerError("Wrong field");
                }
            }
        }

        if ($filtersOR) {
            foreach ($filtersOR as $filter) {
                $solrKey = $schemaManagerService->translateToSolrKey(key($filter));
                if ($solrKey) {
                    $qOR[] = $solrKey . ':' . str_replace(' ', '\ ', $filter->{key($filter)});
                } else {
                    throw new dam_exceptions_InternalServerError("Wrong field");
                }
            }
        }

        $q[] = "instance_s:" . $instance;
        $q[] = "NOT title_collectionFolder_s:*";
        $q[] = "NOT is_contained_i:1";

        $query = implode(' AND ', $q);

        if ($qOR) {
            $query .= ' AND (' . implode(' OR ', $qOR) . ')';
        }

        return $query;
    }

    public function createSolrSort($sortParams)
    {
        $sortField = $sortParams && $sortParams->field ? $sortParams->field : __Config::get('dam.search.defaultSortField');
        $sortOrder = $sortParams && $sortParams->order ? $sortParams->order : 'ASC';

        $schemaManagerService = $this->application->retrieveProxy('dam.services.SchemaManagerService');
        $sortkey = $schemaManagerService->translateToSolrKey($sortField);
        return $sortkey . ' ' . $sortOrder;
    }

    public function createSolrFacets($facetParams)
    {
        if (!$facetParams) {
            return null;
        }

        $ret = $this->preprocessFacetParams($facetParams);

        return implode(
            " AND ",
            array_map(
                function ($solrKey, $value) {
                    return "($solrKey:" . implode(" ", array_map(array($this, "escapeStringForSolrQuery"), $value)) . ")";
                },
                array_keys($ret),
                array_values($ret)
            )
        );
    }

    private function preprocessFacetParams($facetParams)
    {
        $ret = array();

        $schemaManagerService = $this->application->retrieveProxy('dam.services.SchemaManagerService');

        foreach ($facetParams as $facetParam) {
            foreach ($facetParam as $field => $value) {
                $solrKey = $schemaManagerService->translateToSolrKey($field);

                if (!$solrKey) {
                    throw new dam_exceptions_InternalServerError("Unexpected field during facet query creation: " . $field);
                }

                if (is_string($value) && $value{0}==='-') {
                    $value = substr($value, 1);
                    $solrKey = 'NOT '.$solrKey;
                }

                $v = is_array($value) || is_object($value) ? array_values((array)$value) : array($value);
                $ret[$solrKey] = key_exists($solrKey, $ret) ? array_unique(array_merge($ret[$solrKey], $v)) : $v;
            }
        }

        return $ret;

    }

    private function escapeStringForSolrQuery($string)
    {
        $specialChars = explode(" ", "\\ & | + - ! ( ) { } [ ] ^ \" ~ * ? : /");

        foreach ($specialChars as $char) {
            $string = str_replace($char, "\\$char", $string);
        }
        $string = str_replace(" ", "\\ ", $string);

        return $string;
    }
}
