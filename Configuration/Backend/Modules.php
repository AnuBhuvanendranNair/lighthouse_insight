<?php

declare(strict_types=1);

use Anubit\LighthouseInsight\Controller\PageSpeedController;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$parent = GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() >= 14 ? 'content' : 'web';

return [
    'lighthouse_insight' => [
        'parent' => $parent,
        'access' => 'user',
        'path' => '/module/content/lighthouse-insight',
        'iconIdentifier' => 'lighthouse-insight-module',
        'labels' => 'LLL:EXT:lighthouse_insight/Resources/Private/Language/locallang_mod.xlf',
        'navigationComponent' => '@typo3/backend/tree/page-tree-element',
        'routes' => [
            '_default' => [
                'target' => PageSpeedController::class . '::indexAction',
            ],
            'analyze' => [
                'target' => PageSpeedController::class . '::analyzeAction',
                'methods' => ['POST'],
            ],
        ],
    ],
];
