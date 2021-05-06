<?php

class dam_exceptions_BadRequest extends Exception{

    public $message;

    function __construct($message)
    {
        $this->httpStatus = 400;
        $this->message = $message;
    }
}