<?php


namespace SimpleADT\Match;


use InvalidArgumentException;
use SimpleADT\Exception\PatternMatchException;

final class MatchExpression
{
    /** @var mixed */
    private $target = null;

    /** @var array<string, callable>  */
    private $caseExpr = [];
    /**
     * @var \ReflectionObject
     */
    private $adtClass = null;

    protected function __construct(\ReflectionClass $adtClass, $target, string $caseClass, \Closure $expr) {
        $this->adtClass = $target;
        $this->target = $target;
        $this->caseExpr = [
            $caseClass => $expr
        ];
    }

    public static function singleton(\ReflectionClass $adtClass, $target, string $caseClass, \Closure $expr) :MatchExpression {
        if (!$adtClass->isInstance($target)) {
            throw new InvalidArgumentException("Argument 2 passed to ". self::class."::".__METHOD__.
                " is Not an instance of ".$adtClass->getNamespaceName()
            );
        }
        $instance = new self($adtClass, $target, $caseClass, $expr);
        $instance->checkCaseClass($caseClass);
        return $instance;
    }

    public function case(string $caseClass, $f) :MatchExpression {
        $this->checkCaseClass($caseClass);
        $this->caseExpr[$caseClass] = $f;
        return $this;
    }

    private function checkCaseClass(string $caseClass) :void {
        $adtClassName = $this->adtClass->getNamespaceName();

        $ok = true;
        try {
            $method = $this->adtClass->getMethod($caseClass);
            if (!$method->isStatic()) {
                $hint = "Hint: Consider making the method {$adtClassName}::{$caseClass} static.";
                $ok = false;
            }
            if (!preg_match("/@adt-constructor\s*$/", $method->getDocComment())) {
                $hint = "Hint: Consider annotating the method {$adtClassName}::{$caseClass} with `@adt-constructor` in the Doc block.";
                $ok = false;
            }
        }
        catch (\ReflectionException $e) {
            $hint = "The class {$adtClassName} does not have method {$caseClass}. ".PHP_EOL.
                "Hint: Consider implementing the method {$adtClassName}::{$caseClass} as static method, and ".
                "annotate with `@adt-constructor` in the Doc block.";
            $ok = false;
        }

        if (!$ok) {
            trigger_error("Unknown data constructor {$caseClass}.".PHP_EOL. $hint, E_USER_WARNING);
        }
    }

    public function default($f) {
        $this->caseExpr["_"] = $f;
    }

    public function done() {
        foreach ($this->caseExpr as $case => $expr) {
            if ($case === "_") {
                return $expr();
            }

            else if ($this->target instanceof $case) {
                return $this->runCaseExpr($expr);
            }
        }
        throw new PatternMatchException("Failed pattern match at ");

    }

    private function setADTClass(\ReflectionObject $reflection)
    {
        $this->adtClass = $reflection;
    }


    private function runCaseExpr($expr) {
        try {
            $reflection = new \ReflectionFunction($expr);
        }
        catch (\Throwable $e) {
            throw new \RuntimeException("Should not happen exception");
        }

        try {
            $args = [];
            foreach ($reflection->getParameters() as $parameter) {
                $prop = $this->adtClass->getProperty($parameter->getName());
                $prop->setAccessible(true);
                $args[] = $prop->getValue($this->target);
            }
            return $expr(...$args);
        }
        catch (\Throwable $e) {
            throw new PatternMatchException("Failed to reproduce data constructor arguments", $e->getCode(), $e->getPrevious());
        }
    }
}