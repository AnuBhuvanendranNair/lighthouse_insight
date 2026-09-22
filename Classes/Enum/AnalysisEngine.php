<?php

declare(strict_types=1);

namespace Anubit\LighthouseInsight\Enum;

use Anubit\LighthouseInsight\Utility\Translator;

enum AnalysisEngine: string
{
    case GoogleApi = 'google_api';
    case LocalLighthouse = 'local_lighthouse';

    public function getLabel(): string
    {
        return Translator::translate(match ($this) {
            self::GoogleApi => 'engine.googleApi',
            self::LocalLighthouse => 'engine.localLighthouse',
        });
    }
}
