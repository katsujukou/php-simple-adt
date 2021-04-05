<?php


namespace SimpleADT\Command;


//use PHP_Parallel_Lint\PhpConsoleColor\ConsoleColor;
use SimpleADT\Exception\ComposerException;

class Base
{
//    /**
//     * @var ConsoleColor
//     */
//    protected $consoleColor;
    /**
     * @var string
     */
    protected $composer;

    /**
     * Base constructor.
     * @param ConsoleColor $consoleColor
     */
    public function __construct(/*$consoleColor*/) {
//        $this->consoleColor = $consoleColor;
    }

    /**
     * @param $command
     * @param $args
     * @return string[]
     * @throws ComposerException
     */
    protected function runComposer($command, $args=[]) :array {
        $status = 0;
        $output = [];
        exec(__COMPOSER__." $command ". implode(" ", $args). " 2>&1",$output, $status);
        if ($status) {
            throw new ComposerException($command, $args, $output, $status);
        }
        return $output;
    }
}