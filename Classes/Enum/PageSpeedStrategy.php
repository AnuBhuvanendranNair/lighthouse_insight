<?php

declare(strict_types=1);

namespace Anubit\LighthouseInsight\Enum;

use Anubit\LighthouseInsight\Utility\Translator;

enum PageSpeedStrategy: string
{
    case Mobile = 'mobile';
    case Desktop = 'desktop';

    public function getLabel(): string
    {
        return Translator::translate(match ($this) {
            self::Mobile => 'strategy.mobile',
            self::Desktop => 'strategy.desktop',
        });
    }
}
