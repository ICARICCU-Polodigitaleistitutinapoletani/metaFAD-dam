<?php
abstract class dam_exceptions_AbstractHttpException extends Exception
{
    public $httpStatus;

    function __construct($message, $httpStatus)
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
    }
}
