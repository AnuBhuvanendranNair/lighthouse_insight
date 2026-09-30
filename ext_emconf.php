<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Lighthouse Insight',
    'description' => 'Run Google PageSpeed Insights analyses from the TYPO3 backend.',
    'category' => 'module',
    'author' => 'Anu Bhuvanendran Nair',
    'author_email' => '',
    'state' => 'stable',
    'clearCacheOnLoad' => false,
    'version' => '1.1.0',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.6.99',
            'typo3' => '13.4.0-14.9.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
