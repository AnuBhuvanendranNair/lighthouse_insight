<?php

declare(strict_types=1);

namespace Anubit\LighthouseInsight\Service;

use Anubit\LighthouseInsight\Exception\PageUrlResolverException;
use Anubit\LighthouseInsight\Utility\Translator;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Routing\InvalidRouteArgumentsException;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\SiteFinder;

final readonly class PageUrlResolver
{
    public function __construct(
        private SiteFinder $siteFinder,
    ) {}

    public function resolve(int $pageUid, ?int $languageUid = null): string
    {
        if ($pageUid <= 0) {
            throw new PageUrlResolverException(Translator::translate('error.noPageSelected'));
        }

        try {
            $site = $this->siteFinder->getSiteByPageId($pageUid);
            $language = $languageUid === null
                ? $site->getDefaultLanguage()
                : $site->getLanguageById($languageUid);

            return (string)$site->getRouter()->generateUri(
                $pageUid,
                ['_language' => $language],
                '',
                RouterInterface::ABSOLUTE_URL
            );
        } catch (SiteNotFoundException|\InvalidArgumentException|InvalidRouteArgumentsException $exception) {
            throw new PageUrlResolverException(Translator::translate('error.pageUrlUnresolvable'), 0, $exception);
        }
    }
}
