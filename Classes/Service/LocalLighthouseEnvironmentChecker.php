<?php

declare(strict_types=1);

namespace Anubit\LighthouseInsight\Service;

use Anubit\LighthouseInsight\Dto\LocalLighthouseEnvironment;
use Anubit\LighthouseInsight\Utility\Translator;
use Symfony\Component\Process\Process;
use TYPO3\CMS\Core\Core\Environment;

final readonly class LocalLighthouseEnvironmentChecker
{
    public function check(): LocalLighthouseEnvironment
    {
        $missing = [];
        $messages = [];

        if (!$this->commandSucceeds(['npx', '--version'])) {
            $missing[] = 'npm/npx';
            $messages[] = Translator::translate('env.installNode');
        }

        if (!$this->hasChrome()) {
            $missing[] = 'Chrome/Chromium';
            $messages[] = Translator::translate('env.installChrome');
        }

        return new LocalLighthouseEnvironment(
            available: $missing === [],
            missingRequirements: $missing,
            messages: $messages,
        );
    }

    private function hasChrome(): bool
    {
        $chromePath = getenv('CHROME_PATH');
        if (is_string($chromePath) && $chromePath !== '' && is_executable($chromePath)) {
            return true;
        }

        return $this->commandSucceeds(['which', 'google-chrome'])
            || $this->commandSucceeds(['which', 'chromium'])
            || $this->commandSucceeds(['which', 'chromium-browser']);
    }

    /**
     * @param list<string> $command
     */
    private function commandSucceeds(array $command, int $timeout = 5): bool
    {
        $process = new Process($command, Environment::getProjectPath(), null, null, $timeout);
        $process->run();

        return $process->isSuccessful();
    }
}
