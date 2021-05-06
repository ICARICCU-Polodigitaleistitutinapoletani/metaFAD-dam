<?php

class dam_exceptions_InternalServerError extends Exception{

    public $message;

    function __construct($message)
    {
        $this->httpStatus = 500;
        $this->message = $message;
    }
}