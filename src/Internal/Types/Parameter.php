<?php


namespace SimpleADT\Internal\Types;


class Parameter
{
    /** @var string */
    private $var;
    /**
     * @var Annotation
     */
    private $annotation;

    /**
     * Parameter constructor.
     * @param string $var
     * @param Annotation $annotation
     */
    public function __construct($var, $annotation)
    {
        $this->var = $var;
        $this->annotation = $annotation;
    }

    /**
     * @return string
     */
    public function getVar()
    {
        return $this->var;
    }

    /**
     * @return Annotation
     */
    public function getAnnotation()
    {
        return $this->annotation;
    }


}