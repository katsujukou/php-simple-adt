<?php


namespace SimpleADT\Internal\Types;


use SimpleADT\Internal\Types\Annotation\TypeLit;
use SimpleADT\Internal\Types\Annotation\TypeParam;

abstract class Annotation
{
    /**
     * @param $type
     * @param $docType
     * @return Annotation
     */
    public static function TypeLit($type, $docType) {
        return new Annotation\TypeLit($type, $docType);
    }

    /**
     * @param string $name
     * @return Annotation
     */
    public static function TypeParam($name) {
        return new Annotation\TypeParam($name);
    }

    public function getType() {
        if ($this instanceof TypeLit) {
            return $this->type;
        }
        else if ($this instanceof TypeParam) {
            return "mixed";
        }
        throw new \RuntimeException("Should not happen exception");
    }

    public function getDocType () {
        if ($this instanceof TypeLit) {
            return $this->docType;
        }
        else if ($this instanceof TypeParam) {
            return $this->name;
        }
        throw new \RuntimeException("Should not happen exception");
    }
}