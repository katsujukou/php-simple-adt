<?php


namespace SimpleADT\Internal;


use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\UnionType;
use PhpParser\NodeVisitorAbstract;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use SimpleADT\Internal\Types\Annotation;
use SimpleADT\Internal\Types\Constraint;
use SimpleADT\Internal\Types\Constructor;
use SimpleADT\Internal\Types\Parameter;
use SimpleADT\Internal\Types\Type;
use SimpleADT\Internal\Types\TypeParameter;

class AdtNodeVisitor extends NodeVisitorAbstract
{
    /**
     * @var string
     */
    private $namespace;

    /**
     * @var Type[]
     */
    private $adt;

    /** @var array<string, string> */
    private $alias = [];

    /**
     * @var PhpDocParser
     */
    private $phpDocParser;
    /**
     * @var Lexer
     */
    private $phpDocLexer;

    /**
     * @var string
     */
    private $targetPhpVersion;

    /**
     * AdtNodeVisitor constructor.
     * @param PhpDocParser $phpDocParser
     * @param Lexer $phpDocLexer
     */
    public function __construct($phpDocParser, $phpDocLexer) {
        $this->phpDocParser = $phpDocParser;
        $this->phpDocLexer = $phpDocLexer;
        $this->targetPhpVersion = "php73";
    }

    public function init() {
        $this->adt = [];
        $this->alias = [];
    }

    public function setTargetPHPVer(string $phpVer) {
        $this->targetPhpVersion = $phpVer;
    }

    /**
     * @param \PhpParser\Node $node
     * @return void|null
     */
    public function leaveNode($node) {
        if ($node instanceof Stmt\Class_) {
            $this->leaveClassStmt($node);
        }
        else if ($node instanceof Stmt\Namespace_) {
            $this->leaveNamespaceNode ($node);
        }
        else if ($node instanceof Stmt\Use_
            || $node instanceof Stmt\UseUse
            || $node instanceof Stmt\GroupUse
        ) {
            $this->leaveUseStmt($node);
        }
    }

    /**
     * @param Stmt\Class_ $node
     */
    private function leaveClassStmt($node) {
        if ($node->extends === null ||
            $node->extends->toLowerString() !== "simpleadt\adt"
        ) {
            return;
        }
        if (!$node->isAbstract()) {
            throw new \Exception("Oops!");
        }

        // Does this class have @template tags in Doc block?
        $tokens = new TokenIterator($this->phpDocLexer->tokenize($node->getDocComment() ?? "/** */"));
        $phpDocNode = $this->phpDocParser->parse($tokens);
        $isPolymorphic = count(array_merge(
            $phpDocNode->getTagsByName('@template'),
            $phpDocNode->getTagsByName('@phpstan-template'),
            $phpDocNode->getTagsByName('@psalm-template'))
            ) > 0;

        $constructors = [];
        foreach ($node->getMethods() as $method) {
            // Data constructor of ADTs must be static
            if ($method->isStatic() === false) {
                continue;
            }
            // Data constructor must have PHPDoc tag `@adt-constructor`
            $tokens = new TokenIterator($this->phpDocLexer->tokenize($method->getDocComment() ?? "/** */"));
            $phpDocNode = $this->phpDocParser->parse($tokens);
            if (count($phpDocNode->getTagsByName('@adt-constructor')) <= 0) {
                continue;
            }

            // Now this method is a data constructor of ADT!
            $extends = "";
            if ($isPolymorphic) {
                $returnTags = $phpDocNode->getReturnTagValues();
                if (count($returnTags) <= 0) {
                    throw new \Exception("Return type is missing!");
                }
                $extends = (string)$returnTags[0]->type;
            }

            $typeParams = [];
            foreach (
                $phpDocNode->getTagsByName('@template') +
                $phpDocNode->getTagsByName('@phpstan-template') +
                $phpDocNode->getTagsByName('@psalm-template') as $templateTag
            ) {
                $tagValue = (string)$templateTag->value;
                $matched = [];
                preg_match("/([a-zA-Z0-9_]+)(\s+of\s+(.*))?/", $tagValue, $matched);
                if (!isset($matched[1])) {
                    continue;
                }
                $typeParams[] = new TypeParameter(
                    $matched[1],
                    isset($matched[3]) ?
                        array_map(function ($constraint) {
                            return new Constraint(trim($constraint));
                            }, explode(",", $matched[3])
                        ) : []);
            }

            $docTypeAnnotations = [];
            foreach ($phpDocNode->getParamTagValues() as $paramTag) {
                $docTypeAnnotations[$paramTag->parameterName] = (string)$paramTag->type;
            }

            $constructors[] = new Constructor(
                $method->isPublic(),
                $typeParams,
                $extends,
                $method->name->toString(),
                array_map(function ($param) use ($docTypeAnnotations, $typeParams) {
                    return $this->processConstructorParam(
                        $param,
                        array_map(function (TypeParameter $typeParam) {
                            return $typeParam->getName();
                        }, $typeParams),
                        $docTypeAnnotations
                    );
                }, $method->getParams())
            );

        }
        $this->adt[] = new Type(
            $node->name->toString(),
            $constructors
        );
    }

    /**
     * @param Param $param
     * @param string[] $typeParams
     * @param array<string, string> $docTypeAnnotations
     * @return Parameter
     */
    private function processConstructorParam ($param, $typeParams, $docTypeAnnotations) {
        $varName = "$". $param->var->name;
        $docType = $docTypeAnnotations[$varName];
        $annotation = in_array($docTypeAnnotations[$varName], $typeParams)
                ? Annotation::TypeParam($docTypeAnnotations[$varName])
                : Annotation::TypeLit($this->toText($param->type), $docType);
        return new Parameter((string)$param->var->name, $annotation);
    }

    /**
     * @param null|Identifier|Name|NullableType|UnionType|string
     */
    private function toText ($type) :string {
        if ($type instanceof NullableType) {
            return "?" . $type->type->name;
        }
        else if ($type instanceof UnionType) {
            return $this->targetPhpVersion === "php80"
                ? array_reduce($type->types, function ($acc, $type) {
                    return $acc . "|" . $type->name;
                }, "")
                : "";
        }
        else if ($type instanceof Name) {
            return $type->toString();
        }
        else if ($type instanceof Identifier) {
            return $type->name;
        }
        return (string)$type->type;
    }

    /**
     * @param Stmt\Namespace_ $node
     */
    private function leaveNamespaceNode($node)
    {
        $this->namespace = $node->name;
    }

    /**
     * @param Stmt\Use_|Stmt\UseUse|Stmt\GroupUse $node
     */
    private function leaveUseStmt($node)
    {
        if ($node instanceof Stmt\Use_) {
            $this->processUseStmts($node->uses[0]);
        }
        else if ($node instanceof Stmt\GroupUse) {
            $prefix = $node->prefix->toString() . "\\";
            foreach ($node->uses as $use) {
                $this->processUseStmts($use, $prefix);
            }
        }
        else if ($node instanceof Stmt\UseUse) {
            $this->processUseStmts($node);
        }
    }

    /**
     * @param Stmt\UseUse $use
     * @param string $prefix
     */
    private function processUseStmts($use, $prefix="") {
        $key = $use->getAlias()->toString();
        $this->alias[$key] = $prefix . $use->name->toString();
    }

    /**
     * @return Type[]
     */
    public function toResolvedAdt() {
        $resolved = [];
        $namespace = str_replace("\\\\", "\\", "\\". $this->namespace);
        foreach ($this->adt as $adt) {
            $resolved[] = new Type(
                implode("\\", ["\\".$this->namespace, $adt->getName()]),
                array_map(function ($constructor) use ($namespace) {
                    $typeParams = array_map(function ($templateTag) use ($namespace) {
                        return $templateTag->getName();
                    }, $constructor->getTemplateTags());

                    $templateTags = array_map(function ($typeParameter) use ($namespace, $typeParams) {
                        /** @var TypeParameter $typeParameter */
                        return new TypeParameter(
                            $typeParameter->getName(),
                            array_map(function ($constraint) use ($namespace, $typeParams) {
                                return new Constraint(
                                    TypeResolver::resolveDocType(
                                        $constraint->getType(),
                                        $this->alias,
                                        $typeParams,
                                        $namespace
                                    )
                                );
                            }, $typeParameter->getConstraints())
                        );
                    }, $constructor->getTemplateTags());
                    $extends = $constructor->getExtends() !== "" ? TypeResolver::resolveDocType(
                        $constructor->getExtends(),
                        $this->alias,
                        $typeParams,
                        $namespace
                    ) : "";
                    $annotations = array_map(function ($parameter) use ($namespace, $typeParams) {
                        $annotation = $parameter->getAnnotation();
                        if ($annotation instanceof Types\Annotation\TypeParam) {
                            return $parameter;
                        }
                        else if ($annotation instanceof Types\Annotation\TypeLit) {
                            return new Parameter(
                                $parameter->getVar(),
                                Annotation::TypeLit(
                                    TypeResolver::resolveType($annotation->getType(), $this->alias),
                                    TypeResolver::resolveDocType(
                                        $annotation->getDocType(),
                                        $this->alias,
                                        $typeParams,
                                        $namespace
                                    )
                                )
                            );
                        }

                        throw new \RuntimeException("Should not happen exception");
                    }, $constructor->getParameters());
                    return new Constructor(
                        $constructor->isPublic(),
                        $templateTags,
                        $extends,
                        $constructor->getName(),
                        $annotations
                    );
                }, $adt->getConstructors())
            );
        }
        return $resolved;
    }

    public function flush()
    {
        $this->adt = [];
        $this->alias = [];
    }

}
