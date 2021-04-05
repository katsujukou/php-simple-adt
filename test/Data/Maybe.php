<?php

namespace Test\Data;


/** @template t */
abstract class Maybe extends \SimpleADT\ADT
{
    /**
     * @adt-constructor
     * @return Maybe<mixed>
     */
    public static function Nothing() :Maybe {
        return self::create();
    }

    /**
     * @adt-constructor
     * @template T
     * @param T $val
     * @return Maybe<T>
     */
    public static function Just($val) :Maybe {
        return self::create($val);
    }

}