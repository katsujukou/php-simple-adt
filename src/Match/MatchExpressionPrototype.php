<?php


namespace SimpleADT\Match;


use InvalidArgumentException;
use SimpleADT\ADT;

class MatchExpressionPrototype
{
    private $target;
    /**
     * @var \ReflectionObject
     */
    private $adtClass;

    public function __construct($target) {
        $reflection = new \ReflectionObject($target);
        if (!$reflection->isSubclassOf(ADT::class)) {
            throw new InvalidArgumentException("Argument of match expression must be subclass of ".ADT::class);
        }

        $this->target = $target;
        $this->adtClass = $reflection;
    }

    /**
     * @param string $caseClass
     * @param \Closure $expr
     * @return MatchExpression
     */
    public function case(string $caseClass, \Closure $expr) :MatchExpression {
        return MatchExpression::singleton($this->adtClass, $this->target, $caseClass, $expr);
    }
}
