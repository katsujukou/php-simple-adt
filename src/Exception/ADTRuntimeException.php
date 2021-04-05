<?php


namespace SimpleADT\Exception;


use Throwable;

class ADTRuntimeException extends \RuntimeException
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}