<?php


namespace SimpleADT\Internal\Types;

class Constructor
{
    /** @var TypeParameter[] */
    private $templateTags;

    /** @var string */
    private $extends;

    /** @var string */
    private $name;

    /** @var Parameter[] */
    private $parameters;

    /** @var bool */
    private $isPublic;

    /**
     * @param bool $isPublic
     * @param TypeParameter[] $templateTags
     * @param string $extends
     * @param string $name
     * @param Parameter[] $parameters
     */
    public function __construct($isPublic, $templateTags, $extends, $name, $parameters)
    {
        $this->isPublic = $isPublic;
        $this->templateTags = $templateTags;
        $this->extends = $extends;
        $this->name = $name;
        $this->parameters = $parameters;
    }

    /**
     * @return TypeParameter[]
     */
    public function getTemplateTags()
    {
        return $this->templateTags;
    }

    /**
     * @return string
     */
    public function getExtends()
    {
        return $this->extends;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return Parameter[]
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @return bool
     */
    public function isPublic(): bool
    {
        return $this->isPublic;
    }

}