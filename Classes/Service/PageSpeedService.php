<?php

declare(strict_types=1);

namespace Anubit\LighthouseInsight\Service;

use Anubit\LighthouseInsight\Dto\PageSpeedResult;
use Anubit\LighthouseInsight\Enum\AnalysisEngine;
use Anubit\LighthouseInsight\Enum\PageSpeedStrategy;
use Anubit\LighthouseInsight\Exception\PageSpeedApiException;
use Anubit\LighthouseInsight\Utility\Translator;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\RequestFactory;

final readonly class PageSpeedService
{
    private const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    public function __construct(
        private RequestFactory $requestFactory,
        private ExtensionConfiguration $extensionConfiguration,
        private PageSpeedResponseParser $responseParser,
        private UrlSecurityValidator $urlSecurityValidator,
        private LoggerInterface $logger,
    ) {}

    public function analyze(string $url, string $strategy): PageSpeedResult
    {
        $strategyEnum = PageSpeedStrategy::tryFrom($strategy);
        if ($strategyEnum === null) {
            throw new PageSpeedApiException(Translator::translate('error.invalidStrategy'));
        }

        if (!$this->urlSecurityValidator->isAllowedPublicHttpUrl($url)) {
            throw new PageSpeedApiException(Translator::translate('error.blockedUrl'));
        }

        $apiKey = $this->getApiKey();
        if ($apiKey === '') {
            throw new PageSpeedApiException(Translator::translate('error.missingApiKey'));
        }

        $requestUrl = self::ENDPOINT . '?' . http_build_query([
            'url' => $url,
            'key' => $apiKey,
            'strategy' => $strategyEnum->value,
        ], '', '&', PHP_QUERY_RFC3986)
            . '&category=performance&category=accessibility&category=best-practices&category=seo';

        try {
            $response = $this->requestFactory->request($requestUrl, 'GET', [
                'timeout' => 30,
                'http_errors' => false,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('PageSpeed Insights request failed.', ['exception' => $exception]);
            throw new PageSpeedApiException(Translator::translate('error.requestTimeout'), 0, $exception);
        }

        $statusCode = $response->getStatusCode();
        $body = (string)$response->getBody();
        if ($statusCode === 429) {
            $this->logger->warning('PageSpeed Insights rate limit exceeded.', ['statusCode' => $statusCode]);
            throw new PageSpeedApiException(Translator::translate('error.rateLimitExceeded'));
        }
        if ($statusCode >= 400) {
            $this->logger->warning('PageSpeed Insights returned an error.', [
                'statusCode' => $statusCode,
                'body' => $this->redactApiKey($body, $apiKey),
            ]);
            throw new PageSpeedApiException(Translator::translate('error.analysisFailed'));
        }

        try {
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->logger->error('PageSpeed Insights returned invalid JSON.', ['exception' => $exception]);
            throw new PageSpeedApiException(Translator::translate('error.malformedResponse'), 0, $exception);
        }

        if (!is_array($payload)) {
            throw new PageSpeedApiException(Translator::translate('error.malformedResponse'));
        }

        return $this->responseParser->parse($payload, $strategyEnum->value, AnalysisEngine::GoogleApi->value);
    }

    private function getApiKey(): string
    {
        try {
            return trim((string)$this->extensionConfiguration->get('lighthouse_insight', 'apiKey'));
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException) {
            return '';
        }
    }

    private function redactApiKey(string $value, string $apiKey): string
    {
        return $apiKey === '' ? $value : str_replace($apiKey, '[redacted]', $value);
    }
}
