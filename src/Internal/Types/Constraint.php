<?php


namespace SimpleADT\Internal\Types;


class Constraint
{

    /** @var string */
    private $type;

    /**
     * Constraint constructor.
     * @param string $type
     */
    public function __construct($type)
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }


}