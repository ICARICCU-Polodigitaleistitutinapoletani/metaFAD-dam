<?php

class dam_rest_models_vo_MediaAllVO
{
    public $total;
    public $itemperpage;
    public $currentpage;
    public $records;

    function __construct($total, $itemPerPage, $currentPage, $records)
    {
        $this->total = $total;
        $this->itemperpage = $itemPerPage;
        $this->currentpage = $currentPage;
        $this->records = $records;
    }

}