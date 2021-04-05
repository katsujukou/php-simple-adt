<?php
declare(strict_types=1);

namespace SimpleADT;

use SimpleADT\Exception\ADTInstanceException;
use SimpleADT\Exception\ADTRuntimeException;

abstract class ADT {
    /**
     * @param mixed... $params
     * @return mixed
     */
    protected static function create(...$params) {
        $traces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $class = $traces[1]["function"];
        $caller = "\\" . $traces[1]["class"];
        $fqcn = implode("\\", [$caller, $class]);
        try {
            $reflectionMethod = new \ReflectionMethod($caller, $class);
            if (!preg_match('/@adt-constructor\s*$/', $reflectionMethod->getDocComment())) {
                throw new ADTInstanceException($caller, $class, $params);
            }
            return new $fqcn(...$params);
        }
        catch (\ReflectionException $exception) {
            throw new ADTRuntimeException(
                "Failed to reflect method ".$caller."::".$class,
                $exception->getCode(),
                $exception->getPrevious()
            );
        }
        catch (\Throwable $error) {
            throw new ADTRuntimeException(
                "Failed to create instance of class ".$fqcn.".",
                $error->getCode(),
                $error->getPrevious()
            );
        }
    }
}