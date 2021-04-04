<?php


namespace SimpleADT\Internal\Types;


class TypeParameter {
    /** @var string */
    private $name;

    /** @var Constraint[] */
    private $constraints;

    /**
     * TypeParameter constructor.
     * @param string $name
     * @param Constraint[] $constraints
     */
    public function __construct($name, $constraints) {
        $this->name = $name;
        $this->constraints = $constraints;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return Constraint[]
     */
    public function getConstraints()
    {
        return $this->constraints;
    }

    public function show()
    {
        $constraints = [];
        foreach ($this->constraints as $constraint) {
            $constraints[] = $constraint->getType();
        }
        return $this->name . (count($constraints) > 0
                ? " of ". implode(",", $constraints)
                : "");
    }


}