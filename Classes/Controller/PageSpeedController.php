<?php

declare(strict_types=1);

namespace Anubit\LighthouseInsight\Controller;

use Anubit\LighthouseInsight\Dto\PageSpeedResult;
use Anubit\LighthouseInsight\Enum\AnalysisEngine;
use Anubit\LighthouseInsight\Enum\PageSpeedStrategy;
use Anubit\LighthouseInsight\Exception\PageSpeedApiException;
use Anubit\LighthouseInsight\Exception\PageUrlResolverException;
use Anubit\LighthouseInsight\Service\LocalLighthouseService;
use Anubit\LighthouseInsight\Service\LocalLighthouseEnvironmentChecker;
use Anubit\LighthouseInsight\Service\PageSpeedService;
use Anubit\LighthouseInsight\Service\PageUrlResolver;
use Anubit\LighthouseInsight\Utility\Translator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsController]
final readonly class PageSpeedController
{
    private const MANUAL_URL_ITEM_NAME = 'manualUrl';
    private const MANUAL_URL_TRIGGER_ID = 'lighthouse-insight-linkbrowser-trigger';

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private UriBuilder $uriBuilder,
        private PageRenderer $pageRenderer,
        private PageUrlResolver $pageUrlResolver,
        private PageSpeedService $pageSpeedService,
        private LocalLighthouseService $localLighthouseService,
        private LocalLighthouseEnvironmentChecker $localLighthouseEnvironmentChecker,
        private FlashMessageService $flashMessageService,
        private LinkService $linkService,
        private LoggerInterface $logger,
    ) {}

    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->renderModule($request);
    }

    public function analyzeAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array)($request->getParsedBody() ?? []);
        $pageUid = (int)($body['id'] ?? $request->getQueryParams()['id'] ?? 0);
        $strategy = (string)($body['strategy'] ?? PageSpeedStrategy::Mobile->value);
        $engine = (string)($body['engine'] ?? AnalysisEngine::GoogleApi->value);
        $manualUrl = trim((string)($body[self::MANUAL_URL_ITEM_NAME] ?? ''));
        $result = null;

        try {
            if (PageSpeedStrategy::tryFrom($strategy) === null) {
                throw new PageSpeedApiException(Translator::translate('error.invalidStrategy'));
            }
            $engineEnum = AnalysisEngine::tryFrom($engine);
            if ($engineEnum === null) {
                throw new PageSpeedApiException(Translator::translate('error.invalidEngine'));
            }

            $resolvedUrl = $this->resolveEffectiveUrl($pageUid, $manualUrl);
            $result = match ($engineEnum) {
                AnalysisEngine::GoogleApi => $this->pageSpeedService->analyze($resolvedUrl, $strategy),
                AnalysisEngine::LocalLighthouse => $this->localLighthouseService->analyze($resolvedUrl, $strategy),
            };
        } catch (PageUrlResolverException|PageSpeedApiException $exception) {
            $this->addFlashMessage($exception->getMessage(), ContextualFeedbackSeverity::ERROR);
        } catch (\Throwable $exception) {
            $this->logger->error('Unexpected PageSpeed module error.', ['exception' => $exception]);
            $this->addFlashMessage(Translator::translate('error.analysisFailedUnexpectedly'), ContextualFeedbackSeverity::ERROR);
        }

        return $this->renderModule($request, $result, $pageUid, $strategy, $engine, $manualUrl);
    }

    private function renderModule(
        ServerRequestInterface $request,
        ?PageSpeedResult $result = null,
        ?int $submittedPageUid = null,
        string $submittedStrategy = PageSpeedStrategy::Mobile->value,
        string $submittedEngine = AnalysisEngine::GoogleApi->value,
        ?string $submittedManualUrl = null,
    ): ResponseInterface {
        $this->pageRenderer->addCssFile('EXT:lighthouse_insight/Resources/Public/Css/backend.css');
        $this->pageRenderer->loadJavaScriptModule('@anubit/lighthouse-insight/pagespeed-submit.js');
        $this->pageRenderer->getJavaScriptRenderer()->addJavaScriptModuleInstruction(
            JavaScriptModuleInstruction::create('@typo3/backend/form-engine/field-control/link-popup.js')
                ->instance('#' . self::MANUAL_URL_TRIGGER_ID)
        );

        $queryParams = $request->getQueryParams();
        $pageUid = $submittedPageUid ?? (int)($queryParams['id'] ?? 0);
        $manualUrl = $submittedManualUrl ?? trim((string)($queryParams[self::MANUAL_URL_ITEM_NAME] ?? ''));
        $resolvedUrl = null;
        $pageRecord = [];

        if ($pageUid > 0) {
            $pageRecord = BackendUtility::readPageAccess($pageUid, $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW)) ?: [];
            if ($pageRecord !== []) {
                try {
                    $resolvedUrl = $this->resolveEffectiveUrl($pageUid, $manualUrl);
                } catch (PageUrlResolverException) {
                    $resolvedUrl = null;
                }
            }
        }

        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle('PageSpeed');
        $view->assignMultiple([
            'pageUid' => $pageUid,
            'pageRecord' => $pageRecord,
            'resolvedUrl' => $resolvedUrl,
            'manualUrl' => $manualUrl,
            'manualUrlItemName' => self::MANUAL_URL_ITEM_NAME,
            'manualUrlTriggerId' => self::MANUAL_URL_TRIGGER_ID,
            'linkBrowserUri' => $this->buildLinkBrowserUri($pageUid),
            'strategy' => PageSpeedStrategy::tryFrom($submittedStrategy)?->value ?? PageSpeedStrategy::Mobile->value,
            'strategies' => PageSpeedStrategy::cases(),
            'engine' => AnalysisEngine::tryFrom($submittedEngine)?->value ?? AnalysisEngine::GoogleApi->value,
            'engines' => AnalysisEngine::cases(),
            'localLighthouseEnvironment' => $this->localLighthouseEnvironmentChecker->check(),
            'result' => $result,
            'analyzeUri' => (string)$this->uriBuilder->buildUriFromRoute('lighthouse_insight.analyze', ['id' => $pageUid]),
        ]);

        return $view->renderResponse('PageSpeed/Index');
    }

    /**
     * Resolves the URL to analyze: an explicit manual link (page or external URL) wins,
     * otherwise falls back to the page's own resolved frontend URL.
     */
    private function resolveEffectiveUrl(int $pageUid, string $manualUrl): string
    {
        if ($manualUrl !== '') {
            return $this->resolveManualUrl($manualUrl);
        }

        return $this->pageUrlResolver->resolve($pageUid);
    }

    private function resolveManualUrl(string $linkValue): string
    {
        try {
            $linkData = $this->linkService->resolve($linkValue);
        } catch (\Throwable $exception) {
            throw new PageUrlResolverException(Translator::translate('error.manualUrlUnresolved'), 0, $exception);
        }

        return match ($linkData['type'] ?? '') {
            LinkService::TYPE_URL => (string)($linkData['url'] ?? ''),
            LinkService::TYPE_PAGE => $this->pageUrlResolver->resolve((int)($linkData['pageuid'] ?? 0)),
            default => throw new PageUrlResolverException(Translator::translate('error.manualUrlUnsupportedType')),
        };
    }

    private function buildLinkBrowserUri(int $pageUid): string
    {
        return (string)$this->uriBuilder->buildUriFromRoute('wizard_link', [
            'P' => [
                'params' => ['allowedTypes' => 'page,url'],
                'table' => 'pages',
                'uid' => $pageUid,
                'pid' => $pageUid,
                'formName' => 'editform',
                'itemName' => self::MANUAL_URL_ITEM_NAME,
            ],
        ]);
    }

    private function addFlashMessage(string $message, ContextualFeedbackSeverity $severity): void
    {
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $message,
            '',
            $severity,
            true
        );
        $this->flashMessageService->getMessageQueueByIdentifier()->enqueue($flashMessage);
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
