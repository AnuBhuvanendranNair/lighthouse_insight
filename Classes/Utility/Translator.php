<?php

declare(strict_types=1);

namespace Anubit\LighthouseInsight\Utility;

final class Translator
{
    private const LANGUAGE_FILE = 'LLL:EXT:lighthouse_insight/Resources/Private/Language/locallang.xlf:';

    public static function translate(string $key): string
    {
        return $GLOBALS['LANG']->sL(self::LANGUAGE_FILE . $key);
    }
}
