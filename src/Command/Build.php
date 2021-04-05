<?php
declare(strict_types=1);

namespace SimpleADT\Command;

use SimpleADT\Exception\ADTRuntimeException;
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
     * @var array<string, string>
     */
    private $classmap;

    /**
     * Build constructor.
     * @param Parser $parser
     * @param Builder $builder
     * @param array $attributes
     */
    public function __construct($parser, $builder, $attributes) {
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
        $this->classmap = [];
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
                fwrite(STDOUT, "Updating classmap file".PHP_EOL);
                if (!$this->updateClassmap()) {
                    fwrite(STDERR,"\e[33m[Warn]\e[0m File autoload_classmap.php does noe exist.");
                }
            }
        }
        catch(\Throwable $error) {
            $errors = array_merge([
                "\e[31m[Error]\e[0m ".$error->getMessage(),
                ""
            ], $error->getTrace());
        }

        if (!isset($errors)) {
            fwrite(STDOUT,
                PHP_EOL.
                "\e[34m[info]\e[0m Build succeeded.".PHP_EOL
            );
            $this->shouldWatch && $this->waitForChanges();
        }
        else {
            fwrite(STDERR,
                PHP_EOL.
                "\e[31m[Error]\e[0m Failed to build.".PHP_EOL
            );
            exit(1);
        }
    }

    /**
     * @return bool
     */
    private function updateClassmap() :bool {
        $path = realpath(__DIR__ . "/../../../../composer/autoload_classmap.php");
        if ($path === false) {
            return false;
        }

        try {
            copy($path, $path . '.bak');
            $orig = file_get_contents($path);
            $modified = preg_replace_callback("/([\s\S]*)(^return array\([\s\S]*)/m", function ($matched) {
                return $matched[1] . <<<CLASSMAP
// @modified by Phalg
\$adtOutputDir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'output';


CLASSMAP . $matched[2];
            }, $orig);
            $modified = trim(preg_replace("/^\);$/m", "", $modified)) . PHP_EOL
                . strtr(var_export($this->classmap, true), [
                    "array (" => "",
                    "'__ADT_OUTPUT_DIR__" => "\$adtOutputDir . '"
                ]) . ";";

            file_put_contents($path . ".php", $modified);
            unlink($path . '.bak');
            return true;
        }
        catch (\Throwable $e) {
            rename($path.'.bak', $path);
            throw new ADTRuntimeException(
                "Failed to update autoload classmap".PHP_EOL.$e->getMessage(),
                $e->getCode(),
                $e->getPrevious()
            );
        }
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
            $classmap = $this->builder->build(
                $adt,
                $this->output . DIRECTORY_SEPARATOR . str_replace("\\", "_", $fullyQualifiedClassname),
                $this->phpVersion
            );

            if ($this->updateCache($fullyQualifiedClassname, $path, $contentHash)) {
                $this->compiled++;
                $this->classmap = array_merge($this->classmap, $classmap);
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
