<?php


namespace SimpleADT\Command;


class Install
{
    public static function postInstall() {
        $outputPath = __DIR__. "/../../output";
        if (!is_dir($outputPath)) {
            mkdir($outputPath);
        }
    }

}