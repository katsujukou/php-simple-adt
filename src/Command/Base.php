<?php


namespace SimpleADT\Command;


use PHP_Parallel_Lint\PhpConsoleColor\ConsoleColor;

class Base
{
    /**
     * @var ConsoleColor
     */
    protected $consoleColor;
    /**
     * @var string
     */
    protected $composer;

    /**
     * Base constructor.
     * @param ConsoleColor $consoleColor
     */
    public function __construct($consoleColor) {
        $this->consoleColor = $consoleColor;
    }

    /**
     * @param $command
     * @param $args
     * @return false|string
     */
    protected function runComposer($command, $args=[]) {
        $status = 0;
        $output = [];
        exec("composer $command". implode(" ", $args). "",$output, $status);
        echo "status: ".$status;
    }
}