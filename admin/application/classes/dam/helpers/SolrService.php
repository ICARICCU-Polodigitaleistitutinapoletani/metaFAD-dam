<?php

class dam_helpers_SolrService extends PinaxObject
{
    protected $schemaManagerService;

    function __construct()
    {
        $application = pinax_ObjectValues::get('org.pinax', 'application');
        $this->schemaManagerService = $application->retrieveProxy('dam.services.SchemaManagerService');
    }

    public function delete($id)
    {
        $url = __Config::get('dam.solr.url') . 'update';
        $requestPayload = array('delete' => array('query' => 'id:' . $id));
        $request = pinax_ObjectFactory::createObject(
            "pinax.rest.core.RestRequest",
            $url . "?wt=json&commit=true",
            'POST',
            is_string($requestPayload) ? $requestPayload : @json_encode($requestPayload),
            "application/json"
        );
        $request->setTimeout(1000);
        $request->execute();
        return $id;
    }

    public function createSolrDocument($document, $percentage = null, $name = null)
    {
        $solrMapperHelper = __ObjectFactory::createObject("dam.helpers.SolrMapper");
        $solrDocument = $solrMapperHelper->mapMediaToSolr($document);

        if (($percentage || $percentage == 0) && $name && $percentage != 100) {
            $solrDocument->percentage_i = $percentage;
            $solrDocument->bytestream_batch_s = $name;
        } else if ($percentage != 100) {
            $url = __Config::get('dam.solr.url') . 'select';
            $searchQuery = array();
            $searchQuery['indent'] = 'on';
            $searchQuery['wt'] = 'json';
            $searchQuery['q'] = 'id:' . $document->document_id;
            $searchQuery['fl'] = 'percentage_i,bytestream_batch_s';
            $postBody = self::buildHttpQuery($searchQuery);

            $request = pinax_objectFactory::createObject('pinax.rest.core.RestRequest',
                $url,
                'POST',
                $postBody,
                'application/x-www-form-urlencoded');
            $request->setTimeout(1000);
            $request->setAcceptType('application/json');
            $request->execute();
            $solrResponse = json_decode($request->getResponseBody());
            $solrDocument->percentage_i = $solrResponse->response->docs[0]->percentage_i;
            $solrDocument->bytestream_batch_s = $solrResponse->response->docs[0]->bytestream_batch_s;
            if ($solrResponse->response->docs[0]->bytestream_last_update_s) {
                $solrDocument->bytestream_last_update_s = $solrResponse->response->docs[0]->bytestream_last_update_s;
            }
        } else {
            $solrDocument->percentage_i = null;
            $solrDocument->bytestream_batch_s = null;
        }

        return $this->add($solrDocument);
    }

    private function buildHttpQuery($searchQuery)
    {
        $temp = array_merge($searchQuery, array());
        $url = "";
        unset($temp['url']);
        unset($temp['action']);
        foreach ($searchQuery as $k => $v) {
            if (is_array($v)) {
                if ($k == 'facet.field' || $k == 'fq') {
                    foreach ($v as $v1) {
                        $url .= $k . '=' . $v1 . '&';
                    }
                    unset($temp[$k]);
                } else {
                    $temp[$k] = implode($v, ',');
                }
            }
        }

        return $url . http_build_query($temp);
    }

    public function add($document)
    {
        $url = __Config::get('dam.solr.url') . 'update';
        $requestPayload = array('add' => array('doc' => $document));

        $request = pinax_ObjectFactory::createObject(
            "pinax.rest.core.RestRequest",
            $url . "?wt=json&commit=true",
            'POST',
            is_string($requestPayload) ? $requestPayload : @json_encode($requestPayload),
            "application/json"
        );
        $request->setTimeout(1000);
        $request->execute();

        return $document->id;
    }

    // Refactoring

    public function search($page, $query, $facets, $sort, $resultsForPage = null, $facetEnabled = true)
    {
        $url = __Config::get('dam.solr.url') . 'select';
        $resultsForPage = $resultsForPage ? $resultsForPage : __Config::get('dam.solr.rowsPerPage');
        $searchQuery['q'] = $query;
        if ($facets) {
            $searchQuery['fq'] = $facets;
        }
        $searchQuery['sort'] = $sort;
        if ($page != 0) {
            $searchQuery['start'] = ($page - 1) * $resultsForPage;
            $searchQuery['rows'] = $resultsForPage;
        }
        $searchQuery['wt'] = 'json';
        if ($facetEnabled) {
            $searchQuery['facet'] = 'true';
            $searchQuery['facet.limit'] = __Config::get('dam.facet.limit');
            $searchQuery['facet.field'] = $this->schemaManagerService->getSolrFacetFields();
            $searchQuery['facet.mincount'] = 0;
        }
        $postBody = self::buildHttpQuery($searchQuery);

        $request = pinax_objectFactory::createObject('pinax.rest.core.RestRequest',
            $url,
            'POST',
            $postBody,
            'application/x-www-form-urlencoded');
        $request->setTimeout(1000);
        $request->setAcceptType('application/json');
        $request->execute();
        $solrResponse = json_decode($request->getResponseBody());
        return $solrResponse;
    }

    public function autocomplete($instance, $field, $value)
    {
        $url = __Config::get('dam.solr.url') . 'select';
        $searchQuery['q'] = "instance_s:" . $instance;
        $searchQuery['rows'] = 0;
        $searchQuery['wt'] = 'json';
        $searchQuery['facet'] = 'true';
        $searchQuery['facet.field'] = $field;
        $searchQuery['facet.mincount'] = 1;
        $searchQuery['facet.prefix'] = $value;
        $searchQuery['facet.sort'] = 'index';
        $postBody = self::buildHttpQuery($searchQuery);
        $request = pinax_objectFactory::createObject('pinax.rest.core.RestRequest',
            $url,
            'POST',
            $postBody,
            'application/x-www-form-urlencoded');
        $request->setTimeout(1000);
        $request->setAcceptType('application/json');
        $request->execute();
        $solrResponse = json_decode($request->getResponseBody());
        return $solrResponse;
    }

    public function publish($document)
    {
        $url = __Config::get('dam.solr.url') . 'update';
        $document = (object)array_filter((array)$document);
        $requestPayload = array('add' => array('doc' => $document));
        $request = pinax_ObjectFactory::createObject(
            "pinax.rest.core.RestRequest",
            $url . "?wt=json&commit=true",
            'POST',
            is_string($requestPayload) ? $requestPayload : @json_encode($requestPayload),
            "application/json"
        );
        $request->setTimeout(1000);
        $request->execute();
    }
}
