<?php


namespace SimpleADT\Exception;


use Throwable;

class ADTInstanceException extends \RuntimeException
{
    /** @var string */
    private $adt;
    /** @var string */
    private $dataConstructor;
    /** @var array */
    private $params;

    public function __construct($adtClass, $dataConstructor, $params, $code = 0, Throwable $previous = null)
    {
        parent::__construct("Call to ADT create method from outside of ADT data constructor method", $code, $previous);
        $this->adt = $adtClass;
        $this->dataConstructor = $dataConstructor;
        $this->params = $params;
    }

    /**
     * @return string
     */
    public function getAdt(): string
    {
        return $this->adt;
    }

    /**
     * @return string
     */
    public function getDataConstructor(): string
    {
        return $this->dataConstructor;
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }
}
