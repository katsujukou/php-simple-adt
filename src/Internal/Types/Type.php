<?php


namespace SimpleADT\Internal\Types;


class Type
{
    /** @var string */
    private $name;

    /** @var Constructor[] */
    private $constructors;

    /**
     * ADT constructor.
     * @param string $name
     * @param Constructor[] $constructors
     */
    public function __construct($name, $constructors)
    {
        $this->name = $name;
        $this->constructors = $constructors;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return Constructor[]
     */
    public function getConstructors()
    {
        return $this->constructors;
    }


}