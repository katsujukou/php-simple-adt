<?php
declare(strict_types=1);

namespace SimpleADT\Command;

//use PHP_Parallel_Lint\PhpConsoleColor\ConsoleColor;
use SimpleADT\Exception\ComposerException;
use SimpleADT\Internal\Builder;
use SimpleADT\Internal\Parser;

class Build extends Base {

    /** @var bool */
    private $initialized;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var Builder
     */
    private $builder;

    /** @var array  */
    private $attributes;
    /**
     * @var string
     */
    private $output;
    /**
     * @var bool
     */
    private $shouldWatch;
    /**
     * @var bool
     */
    private $force;
    /**
     * @var bool
     */
    private $noDumpAutoload;
    /**
     * @var string
     */
    private $phpVersion;
    /**
     * @var array
     */
    private $cacheDb;
    /**
     * @var int
     */
    private $compiled;

    /**
     * Build constructor.
     * @param Parser $parser
     * @param Builder $builder
     * @param array $attributes
     */
    public function __construct($parser, $builder, $attributes) {
        parent::__construct();
        $this->parser = $parser;
        $this->builder = $builder;
        $this->attributes = $attributes;
    }

    /**
     * @param bool $initialized
     * @return bool
     */
    private function initialize ($initialized) {
        $this->compiled = 0;
        if ($initialized) {
            return $initialized;
        }

        $this->output = __DIR__."/../../output";
        $this->shouldWatch = $this->attributes['watch'];
        $this->force = $this->attributes['force'];
        $this->noDumpAutoload = $this->attributes['no-dump-autoload'];
        $this->phpVersion = $this->attributes['php-version'];

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
        try {
            foreach ($srcDirectories as $srcDirectory) {
                if (is_dir($srcDirectory)) {
                    $result = $result && $this->processDirectory($srcDirectory, $result);
                } else if (is_file($srcDirectory)) {
                    $result = $result && $this->processFile($srcDirectory);
                }

                if (!$result) break;
            }

            if ($result && $this->compiled > 0 && $this->noDumpAutoload === false) {
                fwrite(STDOUT, "Updating classmap".PHP_EOL);
                $this->updateClassmap();
            }
        }
        catch (ComposerException $exception) {
            $errors = $exception->renderError($this->consoleColor);
        }
        catch(\Throwable $error) {
            $errors = array_merge([
                "[ERROR] ".$error->getMessage(),
                ""
            ], $error->getTrace());
        }

        if (!isset($errors)) {
            fwrite(STDOUT,
                PHP_EOL.
                "[info] Build succeeded.".PHP_EOL
            );
            $this->shouldWatch && $this->waitForChanges();
        }
        else {
            fwrite(STDERR,
                PHP_EOL.
                "[Error] Failed to build.".PHP_EOL
            );
            exit(1);
        }
    }

    private function updateClassmap() {
        $classmap = require(__DIR__."../../../../composer/autoload_classmap.php");
    }

    /**
     * @param string $path
     * @param bool $shouldContinue
     * @return bool
     */
    private function processDirectory(string $path, bool $shouldContinue): bool
    {
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
    private function processFile (string $path): bool
    {
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

        fwrite(STDOUT, "Compiling ". $fullyQualifiedClassname.PHP_EOL);

        $this->parser->init();
        $adtList = $this->parser->parse($content);
        foreach($adtList as $adt) {
            $this->builder->build(
                $adt,
                $this->output . DIRECTORY_SEPARATOR . str_replace("\\", "_", $fullyQualifiedClassname),
                $this->phpVersion
            );

            if ($this->updateCache($fullyQualifiedClassname, $path, $contentHash)) {
                $this->compiled++;
                return true;
            }

            return false;
        }
    }

    public function waitForChanges () {

    }

    /**
     * @param string $fullyQualifiedClassname
     * @param string $path
     * @param string $contentHash
     * @return bool|int
     */
    private function updateCache(string $fullyQualifiedClassname, string $path, string $contentHash)
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
     * @return bool|int
     */
    private function exportCacheDb()
    {
        return file_put_contents(
            $this->output.DIRECTORY_SEPARATOR."cache-db.json",
            json_encode($this->cacheDb)
        );
    }
}