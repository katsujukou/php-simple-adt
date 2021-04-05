<?php
if (!function_exists('match_')) {
    function match_ ($target) :\SimpleADT\Match\MatchExpressionPrototype {
        return new \SimpleADT\Match\MatchExpressionPrototype($target);
    }
}
