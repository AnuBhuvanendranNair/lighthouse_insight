<?php

declare(strict_types=1);

use Anubit\LighthouseInsight\Controller\PageSpeedController;

return [
    'lighthouse_insight' => [
        'parent' => 'content',
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
