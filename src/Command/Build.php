<?php
declare(strict_types=1);

namespace SimpleADT\Command;

use PHP_Parallel_Lint\PhpConsoleColor\ConsoleColor;
use SimpleADT\Internal\Builder;
use SimpleADT\Internal\Parser;

class Build extends Base {

    /** @var bool */
    private $initialized;

    /** @var bool */
    private $shouldWatch;

    /** @var string */
    private $output;

    /** @var array */
    private $cacheDb;
    /**
     * @var bool
     */
    private $force;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var Builder
     */
    private $builder;
    /**
     * @var string
     */
    private $phpVersion;

    /**
     * Build constructor.
     * @param string $output
     * @param bool $force
     * @param bool $shouldWatch
     * @param string $phpVersion
     * @param Parser $parser
     * @param Builder $builder
     * @param ConsoleColor $consoleColor
     */
    public function __construct(
        $output,
        $force,
        $shouldWatch,
        $phpVersion,
        $parser,
        $builder,
        $consoleColor
    ) {
        parent::__construct($consoleColor);
        $this->initialized = false;
        $this->shouldWatch = $shouldWatch;
        $this->force = $force;
        $this->output = $output;
        $this->phpVersion = $phpVersion;
        $this->parser = $parser;
        $this->builder = $builder;
        $this->cacheDb = [];
    }

    /**
     * @param bool $initialized
     * @return bool
     */
    private function initialize ($initialized) {
        if ($initialized) {
            return $initialized;
        }

        if (is_dir($this->output) &&
            is_file($this->output. "/cache-db.json")
        ) {
            $cacheDb = json_decode(file_get_contents($this->output . "/cache-db.json"), true);
            if (is_array($cacheDb)) {
                $this->cacheDb = $cacheDb;
            }
        }

        return true;
    }

    /**
     * @param string[] $srcDirectories
     */
    public function run ($srcDirectories) :void {
        $result = $this->initialized = $this->initialize($this->initialized);

        foreach ($srcDirectories as $srcDirectory) {
            if (is_dir($srcDirectory)) {
                $result = $result && $this->processDirectory($srcDirectory, $result);
            }
            else if (is_file($srcDirectory)) {
                $result = $result && $this->processFile($srcDirectory);
            }

            if (!$result) break;
        }

        if ($result) {
            echo $this->consoleColor->apply("color_33", "[info]"). " Compilation finished.".PHP_EOL;
            echo $this->consoleColor->apply("color_33", "[info]"). " Running `composer dump-autoload`. This may take a few minutes...".PHP_EOL;
            $this->runComposer("dump-autoload");

            echo $this->consoleColor->apply("color_33", "[info]"). " Build succeeded.".PHP_EOL;
        }
        else {
            echo $this->consoleColor->apply("color_9", "[Error]"). " Failed to build.".PHP_EOL;
        }

        if ($this->shouldWatch) {
            $this->waitForChanges();
        }
    }

    /**
     * @param string $path
     * @param bool $shouldContinue
     * @return bool
     */
    private function processDirectory($path, $shouldContinue) {
        $files = scandir($path);

        foreach ($files as $file) {
            if ($file === "." || $file === "..") {
                continue;
            }

            $file = realpath ($path.DIRECTORY_SEPARATOR.$file);
            $shouldContinue = $shouldContinue && (
                is_dir($file) ?
                    $this->processDirectory($file, $shouldContinue) :
                    $this->processFile($file)
                );

            if (!$shouldContinue) {
                break;
            }
        }

        return $shouldContinue;
    }

    /**
     * @param string $path
     * @return bool
     */
    private function processFile ($path) {
        $content = file_get_contents($path);

        $matched = [];
        preg_match("/abstract\s+class\s+([a-zA-Z_][a-zA-Z0-9_]*)\s+extends\s+(\\\\?SimpleADT\\\\)?ADT/", $content, $matched);
        if (!$matched) {
            // Not an ADT, skip.
            return true;
        }
        $typename = $matched[1];
        preg_match("/namespace\s+(.*)(\\{|;)/", $content, $matched);
        $namespace = $matched[1] ?? "";
        $fullyQualifiedClassname = "\\" . $namespace . "\\" .$typename;
        $contentHash = hash('sha256', $content);

        if (!isset($this->cacheDb[$fullyQualifiedClassname])) {
            $this->cacheDb[$fullyQualifiedClassname] = [];
        }
        if (!$this->force &&
            isset($this->cacheDb[$fullyQualifiedClassname][$path]) &&
            isset($this->cacheDb[$fullyQualifiedClassname][$path][1]) &&
            $this->cacheDb[$fullyQualifiedClassname][$path][1] === $contentHash
        ) {
            return true;
        }

        echo  "Compiling ". $fullyQualifiedClassname.PHP_EOL;
        $adtList = $this->parser->parse($content);
        foreach($adtList as $adt) {
            $this->builder->build(
                $adt,
                $this->output . DIRECTORY_SEPARATOR . str_replace("\\", "_", $fullyQualifiedClassname),
                $this->phpVersion
            );

            return $this->updateCache($fullyQualifiedClassname, $path, $contentHash);
        }
    }

    public function waitForChanges () {

    }

    /**
     * @param string $fullyQualifiedClassname
     * @param string $path
     * @param string $contentHash
     * @return bool
     */
    private function updateCache($fullyQualifiedClassname, $path, $contentHash)
    {
        if (!isset($this->cacheDb[$fullyQualifiedClassname])) {
            $this->cacheDb[$fullyQualifiedClassname] = [];
        }
        $this->cacheDb[$fullyQualifiedClassname][$path] = [
            date('Y-m-dTH:i:s.') . explode(".", microtime())[1],
            $contentHash
        ];

        return $this->exportCacheDb();
    }

    /**
     * @return bool
     */
    private function exportCacheDb()
    {
        return file_put_contents(
            $this->output.DIRECTORY_SEPARATOR."cache-db.json",
            json_encode($this->cacheDb)
        );
    }
}