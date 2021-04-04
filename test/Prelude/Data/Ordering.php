<?php

namespace Test\Prelude\Data;

use SimpleADT\ADT;

abstract class Ordering extends ADT
{
    /**
     * @adt-constructor
     * @return Ordering
     */
    public static function EQ() :Ordering {
        return self::create();
    }

    /**
     * @adt-constructor
     * @return Ordering
     */
    public static function LT() :Ordering {
        return self::create();
    }

    /**
     * @adt-constructor
     * @return Ordering
     */
    public static function GT() :Ordering {
        return self::create();
    }

}