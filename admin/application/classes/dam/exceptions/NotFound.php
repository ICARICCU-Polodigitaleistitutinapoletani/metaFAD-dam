<?php

class dam_exceptions_NotFound extends Exception{


    function __construct()
    {
        $this->httpStatus = 404;
    }
}