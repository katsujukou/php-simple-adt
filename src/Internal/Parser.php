<?php
declare(strict_types=1);

namespace SimpleADT\Internal;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TypeParser;
use SimpleADT\Internal\Types\Type;

class Parser {
    /**
     * @var \PhpParser\Parser
     */
    private $parser;

    /**
     * Parser constructor.
     * @param \PhpParser\Parser $parser
     */
    public function __construct($parser) {
        $this->parser = $parser;
    }

    /**
     * @param string $code
     * @return Type[]
     */
    public function parse ($code) {
        $phpAst = $this->parser->parse($code);

        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor(new NameResolver);
        $adtVisitor = new AdtNodeVisitor(
            new PhpDocParser(new TypeParser(), new ConstExprParser()),
            new Lexer()
        );
        $nodeTraverser->addVisitor($adtVisitor);
        $nodeTraverser->traverse($phpAst);
        return $adtVisitor->toResolvedAdt();
    }

}