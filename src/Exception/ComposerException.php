<?php


namespace SimpleADT\Exception;


use PHP_Parallel_Lint\PhpConsoleColor\ConsoleColor;
use Throwable;

class ComposerException extends \Exception
{
    /**
     * @var string
     */
    protected $command;

    /**
     * @var string[]
     */
    protected $args;

    /**
     * @var string[]
     */
    protected $out;

    /**
     * ComposerException constructor.
     * @param string $command
     * @param string[] $args
     * @param string[] $out
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($command, $args, $out, $code = 0, Throwable $previous = null)
    {
        $this->command = $command;
        $this->args = $args;
        $this->out = $out;
        parent::__construct("Failed to run `composer $command `".implode(" ", $args), $code, $previous);
    }

    /**
     * @param ConsoleColor $consoleColor
     */
    public function renderError (ConsoleColor $consoleColor): array
    {
        $outputs = [];
        $outputs[] = $consoleColor->apply('color_9', "[ERROR] "). $this->message;
        $outputs[] = "";

        return array_merge($outputs, $this->out);
    }

}