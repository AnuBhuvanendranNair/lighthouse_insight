<?php

declare(strict_types=1);

namespace Anubit\LighthouseInsight\Service;

use Anubit\LighthouseInsight\Dto\PageSpeedResult;
use Anubit\LighthouseInsight\Enum\AnalysisEngine;
use Anubit\LighthouseInsight\Enum\PageSpeedStrategy;
use Anubit\LighthouseInsight\Exception\PageSpeedApiException;
use Anubit\LighthouseInsight\Utility\Translator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;

final readonly class LocalLighthouseService
{
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
        private PageSpeedResponseParser $responseParser,
        private LocalLighthouseEnvironmentChecker $environmentChecker,
        private LoggerInterface $logger,
    ) {}

    public function analyze(string $url, string $strategy): PageSpeedResult
    {
        $strategyEnum = PageSpeedStrategy::tryFrom($strategy);
        if ($strategyEnum === null) {
            throw new PageSpeedApiException(Translator::translate('error.invalidStrategy'));
        }

        $environment = $this->environmentChecker->check();
        if (!$environment->available) {
            throw new PageSpeedApiException(Translator::translate('error.localUnavailablePrefix') . ' ' . implode(' ', $environment->messages));
        }

        $temporaryDirectory = Environment::getVarPath() . '/transient';
        if (!is_dir($temporaryDirectory)) {
            mkdir($temporaryDirectory, 0775, true);
        }

        $temporaryReport = tempnam($temporaryDirectory, 'lighthouse-insight-');
        if ($temporaryReport === false) {
            throw new PageSpeedApiException(Translator::translate('error.localTempFileFailed'));
        }

        $command = [
            'npx',
            '--yes',
            $this->getLighthousePackage(),
            $url,
            '--quiet',
            '--output=json',
            '--output-path=' . $temporaryReport,
            '--only-categories=performance,accessibility,best-practices,seo',
            '--form-factor=' . ($strategyEnum === PageSpeedStrategy::Mobile ? 'mobile' : 'desktop'),
            '--screenEmulation.disabled=' . ($strategyEnum === PageSpeedStrategy::Desktop ? 'true' : 'false'),
            '--chrome-flags=' . $this->getChromeFlags(),
        ];

        $process = new Process($command, Environment::getProjectPath(), null, null, $this->getTimeout());

        try {
            $process->run();
        } catch (ProcessTimedOutException $exception) {
            @unlink($temporaryReport);
            $this->logger->error('Local Lighthouse process timed out.', ['exception' => $exception]);
            throw new PageSpeedApiException(Translator::translate('error.localTimeout'), 0, $exception);
        }

        if (!$process->isSuccessful()) {
            $errorOutput = trim($process->getErrorOutput()) ?: trim($process->getOutput());
            @unlink($temporaryReport);
            $this->logger->error('Local Lighthouse process failed.', [
                'exitCode' => $process->getExitCode(),
                'errorOutput' => $errorOutput,
            ]);
            throw new PageSpeedApiException(Translator::translate('error.localAnalysisFailed'));
        }

        $json = @file_get_contents($temporaryReport);
        @unlink($temporaryReport);
        if (!is_string($json) || trim($json) === '') {
            throw new PageSpeedApiException(Translator::translate('error.localNoReport'));
        }

        try {
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->logger->error('Local Lighthouse returned invalid JSON.', ['exception' => $exception]);
            throw new PageSpeedApiException(Translator::translate('error.localMalformedResponse'), 0, $exception);
        }

        if (!is_array($payload)) {
            throw new PageSpeedApiException(Translator::translate('error.localMalformedResponse'));
        }

        return $this->responseParser->parse($payload, $strategyEnum->value, AnalysisEngine::LocalLighthouse->value);
    }

    private function getChromeFlags(): string
    {
        $configuredFlags = $this->getExtensionConfigurationValue('localChromeFlags');
        if ($configuredFlags !== '') {
            return $configuredFlags;
        }

        return '--headless --ignore-certificate-errors --no-sandbox';
    }

    private function getLighthousePackage(): string
    {
        $configuredPackage = $this->getExtensionConfigurationValue('localLighthousePackage');
        if ($configuredPackage !== '') {
            return $configuredPackage;
        }

        return 'lighthouse@10.4.0';
    }

    private function getTimeout(): int
    {
        $configuredTimeout = (int)$this->getExtensionConfigurationValue('localTimeout');
        return $configuredTimeout > 0 ? $configuredTimeout : 120;
    }

    private function getExtensionConfigurationValue(string $path): string
    {
        try {
            return trim((string)$this->extensionConfiguration->get('lighthouse_insight', $path));
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException) {
            return '';
        }
    }
}
