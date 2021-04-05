<?php
declare(strict_types=1);

namespace SimpleADT\Internal;

use PhpParser\ParserFactory;
use SimpleADT\Internal\Types\Annotation;
use SimpleADT\Internal\Types\Type;

class Builder {

    /**
     * @param Type $adt
     * @param $output
     * @param $phpVersion
     * @return array<string, string>
     */
    public function build (
        Type $adt,
        string $output,
        string $phpVersion
    ) :array {
        if (!is_dir($output)) {
            mkdir($output);
        }

        switch ($phpVersion) {
            case "php73":
                return $this->forPhp73($output, $adt);

            case "php74":
            case "php80":
                return $this->forPhp74($output, $adt);

            default:
                return $this->forPhp7x($output, $adt);
        }
    }

    private function forPhp73($output, $adt) {
        return $this->forPhp7x($output, $adt);
    }

    private function forPhp74($output, $adt) {
        return $this->forPhp7x($output, $adt, true);
    }


    /**
     * @param string $outputPath
     * @param Type $adt
     */
    private function forPhp7x($outputPath, $adt, $enableTypedProperty = false) {
        $classmap = [];

        foreach ($adt->getConstructors() as $constructor) {
            $namespace = preg_replace("/^\\\\/", "", $adt->getName());

            $code = [];
            $indent = 0;
            $code = $this->put($code, $indent, "<?php");
            $code = $this->put($code, $indent, "namespace " . $namespace . ";");
            $code = $this->put($code, $indent, "/**");
            foreach ($constructor->getTemplateTags() as $tag) {
                $code = $this->put($code, $indent, " * @template " . $tag->show());
            }
            if ($constructor->getExtends()) {
                $code = $this->put($code, $indent, " * @extends " . $constructor->getExtends());
            }
            $code = $this->put($code, $indent, " */");
            $code = $this->put($code, $indent, "final class " . $constructor->getName() . " extends " . $adt->getName() . " {");
            $indent++;
            $constructorParams = [];
            $constructorDoc = $this->put([], 1, "/**");
            $constructorAssignment = [];
            foreach ($constructor->getParameters() as $parameter) {
                $propertyType = $enableTypedProperty ? $this->phpType($parameter->getAnnotation(), true) : "";
                $code = $this->put($code, $indent, "/** @var ". $parameter->getAnnotation()->getDocType(). " */");
                $code = $this->put($code, $indent, "protected ".$propertyType. " $" . $parameter->getVar().";");

                $constructorDoc = $this->put($constructorDoc, 1,
                    " * @param ".$parameter->getAnnotation()->getDocType() . " $".$parameter->getVar()
                );
                $constructorParams[] = $this->phpType($parameter->getAnnotation(), true) ." $" .$parameter->getVar();
                $constructorAssignment = $this->put($constructorAssignment, 2,
                    '$this->'.$parameter->getVar()." = $" . $parameter->getVar().";"
                );
            }
            $constructorDoc = $this->put($constructorDoc, 1, " */");

            $code = array_merge($code, $constructorDoc);
            $constructorSignature =
                ($constructor->isPublic() ? "public " : "protected ") .
                "function __construct(" . implode(",", $constructorParams).")";

            $code = $this->put($code, $indent, $constructorSignature. " {");
            $code = array_merge($code, $constructorAssignment);
            $code = $this->put($code, $indent, "}");

            foreach ($constructor->getParameters() as $param) {
                $code = $this->put($code, $indent, "/**");
                $code = $this->put($code, $indent, " * @return ".$param->getAnnotation()->getDocType());
                $code = $this->put($code, $indent, " */");
                $upperVar = strtoupper($param->getVar()[0]).substr($param->getVar(), 1);
                $retType = $this->phpType($param->getAnnotation(), true);
                $getterSig = "public function get".$upperVar."()".($retType !== "" ? " : $retType" : "");
                $code = $this->put($code, $indent, $getterSig." {");
                $code = $this->put($code, $indent+1, 'return $this->'.$param->getVar().";");
                $code = $this->put($code, $indent, "}");
            }
            $indent--;
            $code = $this->put($code, $indent, "}");

            $output = $outputPath. DIRECTORY_SEPARATOR. $constructor->getName().".php";
            file_put_contents($output, implode(PHP_EOL, $code));
            $classmap[$namespace."\\".$constructor->getName()] =
                implode(DIRECTORY_SEPARATOR, [
                    "__ADT_OUTPUT_DIR__",
                    str_replace("\\", "_", $adt->getName()),
                    $constructor->getName().".php"
                ]);
        }

        return $classmap;
    }

    /**
     * @param Annotation $annotation
     * @param bool $shouldHideMixed
     * @return string
     */
    private function phpType($annotation, $shouldHideMixed) {
        $type = $annotation->getType();

        if ($shouldHideMixed) {
            return $type !== "mixed" ? $type : "";
        }

        return $type;

    }

    /**
     * @param string[] $code
     * @param int $indent
     * @param string $line
     * @return string[]
     */
    private function put($code, $indent, $line) {
        $code[] = str_repeat("  ", $indent).$line;
        return $code;
    }
}
