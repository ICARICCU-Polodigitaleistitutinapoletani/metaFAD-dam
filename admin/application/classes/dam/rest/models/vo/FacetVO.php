<?php
class dam_rest_models_vo_FacetVO
{
    public $label;
    public $num;

    public function getFacetFromSolr($solrFacet){
        $this->label = $solrFacet->label;
        $this->num = $solrFacet->value;
    }

}