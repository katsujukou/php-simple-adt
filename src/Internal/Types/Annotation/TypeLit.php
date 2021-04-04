<?php


namespace SimpleADT\Internal\Types\Annotation;


use SimpleADT\Internal\Types\Annotation;

final class TypeLit extends Annotation
{
    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $docType;

    /**
     * TypeParam constructor.
     * @param string $type
     * @param string $docType
     */
    public function __construct($type, $docType) {
        $this->type = $type;
        $this->docType = $docType;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getDocType()
    {
        return $this->docType;
    }


}