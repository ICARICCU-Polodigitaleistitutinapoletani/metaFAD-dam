<?php

class dam_exceptions_FallBackException extends Exception
{
    public $message;

    function __construct($message)
    {
        $this->httpStatus = 500;
        $this->message = $message;
    }

    public static function methodDoesNotExists($className, $methodMame)
    {
        return new self(sprintf('Method does not exists: %s::%s', $className, $methodMame), 500);
    }
}
