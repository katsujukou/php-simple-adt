<?php


namespace SimpleADT\Internal;


class TypeResolver
{
    /**
     * @param string $type
     * @param array<string, string> $alias
     * @return string
     */
    public static function resolveType ($type, $alias, $global = "") {
        // return as-is, if is one of the built-ins
        if ($type === "void" ||
            $type === "null" ||
            $type === "bool" ||
            $type === "int" ||
            $type === "float" ||
            $type === "string" ||
            $type === "array" ||
            $type === "callable" ||
            $type === "resource" ||
            $type === "mixed"
        ) {
            return $type;
        }

        // return as-is, if is fully qualified
        if (strpos($type, "\\") === 0) {
            return $type;
        }

        if ($type[0] === '?') {
            return '?'.self::resolveType(substr($type, 1), $alias, $global);
        }

        return $global ."\\". (array_key_exists($type, $alias) ? $alias[$type] : $type);
    }

    /**
     * @param string $type
     * @param array<string, string> $alias
     * @param string[] $ignored
     * @param string $namespace
     * @return string
     */
    public static function resolveDocType($type, $alias, $ignored, $namespace)
    {
        // Test for parametric polymorphic type
        $matched = [];
        preg_match("/([a-zA-Z0-9_\\\\]*)(<(.*)>)?/", $type, $matched);
        $suffix = "";
        if (isset($matched[3])) {
            $resolvedInner = [];
            foreach (explode(",", $matched[3]) as $inner) {
                $resolvedInner[] = self::resolveDocType(trim($inner), $alias, $ignored, $namespace);
            }
            $suffix = "<".implode(",", $resolvedInner).">";
        }

        // Test for homogeneous array (TODO: Record-like array type is WIP!)
        preg_match("/([a-zA-Z0-9_\\\\]*)\\[\]/", $type, $arrayMatched);
        if (isset($arrayMatched[1])) {
            return self::resolveDocType($arrayMatched[1], $alias, $ignored, $namespace)."[]";
        }

        // Test for union type
        preg_match("/\(([a-zA-Z0-9_\\\\\\|\s]*)\)/", $type, $unionMatched);
        if (isset($unionMatched[1])) {
            $union = array_map(function ($t) { return trim($t); }, explode("|", $unionMatched[1]));
            return "(" . implode(" | ", array_map(function ($unionType) use ($alias, $ignored, $namespace) {
                    return self::resolveDocType($unionType, $alias, $ignored, $namespace);
                    }, $union)
                ) . ")";
        }

        if (in_array($matched[1], $ignored)) {
            return $matched[1];
        }

        return self::resolveType($matched[1], $alias, $namespace) . $suffix;
    }

}