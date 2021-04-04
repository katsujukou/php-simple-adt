<?php


namespace SimpleADT\Internal\Types\Annotation;


use SimpleADT\Internal\Types\Annotation;

final class TypeParam extends Annotation
{

    /**
     * @var string
     */
    protected $name;

    /**
     * @param string $name
     */
    public function __construct($name) {
        $this->name = $name;
    }
}