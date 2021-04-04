<?php
declare(strict_types=1);

namespace SimpleADT\Internal;

use PhpParser\NodeTraverser;
use SimpleADT\Internal\Types\Type;

class Parser {
    /**
     * @var \PhpParser\Parser
     */
    private $parser;

    /** @var NodeTraverser  */
    private $nodeTraverser;

    /** @var AdtNodeVisitor */
    private $adtVisitor;

    /**
     * Parser constructor.
     * @param \PhpParser\Parser $parser
     * @param NodeTraverser $nodeTraverser
     * @param AdtNodeVisitor $adtNodeVisitor
     */
    public function __construct($parser, $nodeTraverser, $adtNodeVisitor) {
        $this->parser = $parser;
        $this->nodeTraverser = $nodeTraverser;
        $this->adtVisitor = $adtNodeVisitor;

        $this->nodeTraverser->addVisitor($this->adtVisitor);
    }

    public function init() {
        $this->adtVisitor->flush();
    }

    /**
     * @param string $code
     * @return Type[]
     */
    public function parse ($code) {
        $phpAst = $this->parser->parse($code);
        $this->nodeTraverser->traverse($phpAst);
        return $this->adtVisitor->toResolvedAdt();
    }

}