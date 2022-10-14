<?php

declare(strict_types=1);
/**
 * Replication config.
 */
return [
    'common' => [
        'source' => 'package-template',
        'packages' => [
            'include' => ['*'],
            'exclude' => [],
        ],
        'files' => [
            '.github/CODE_OF_CONDUCT.md',
            '.github/CONTRIBUTING.md',
            '.github/ISSUE_TEMPLATE.md',
            '.github/PULL_REQUEST_TEMPLATE.md',
            '.github/SECURITY.md',
            '.github/FUNDING.yml',
            '.editorconfig',
            'LICENSE.md',
        ],
    ],
    'gitattributes' => [
        'source' => 'package-template',
        'packages' => [
            'include' => ['*'],
            'exclude' => [
                'docs',
                'yii-docker',
                'app',
                'app-api',
            ],
        ],
        'files' => [
            '.gitattributes',
        ],
    ],
    'dependabot' => [
        'source' => 'package-template',
        'packages' => [
            'include' => ['*'],
            'exclude' => [
                'actions',
                'docs',
                'yii-docker',
                'requirements',
            ],
        ],
        'files' => [
            '.github/dependabot.yml',
        ],
    ],
    'scrutinizer' => [
        'source' => 'package-template',
        'packages' => [
            'include' => ['*'],
            'exclude' => [
                'actions',
                'docs',
                'yii-docker',
                'access',
                // apps (they have to report coverage from Codeception)
                'app',
                'app-api',
                'demo',
                'demo-api',
            ],
        ],
        'files' => [
            '.scrutinizer.yml',
        ],
    ],
    'styleci' => [
        'source' => 'package-template',
        'packages' => [
            'include' => ['*'],
            'exclude' => [
                'actions',
                'docs',
                'yii-docker',
                'requirements',
                'yii-debug-frontend',
                'yii-gii-frontend',
            ],
        ],
        'files' => [
            '.styleci.yml',
        ],
    ],
    'bc_' => [
        'source' => 'package-template',
        'packages' => [
            'include' => ['*'],
            'exclude' => [
                'actions',
                'docs',
                'yii-docker',
                'access',
                'aliases',
                'http',
                'json',
                'security',
                'strings',
                'auth',
                'friendly-exception',
                'injector',
                'requirements',

                // apps
                'app',
                'app-api',
                'demo',
            ],
        ],
        'files' => [
            '.github/workflows/bc.yml_',
        ],
    ],
    'build' => [
        'source' => 'package-template',
        'packages' => [
            'include' => ['*'],
            'exclude' => [
                'actions',
                'docs',
                'yii-docker',
                'yii-debug-frontend',
                'yii-gii-frontend',
            ],
        ],
        'files' => [
            '.github/workflows/build.yml',
        ],
    ],
    'mutation' => [
        'source' => 'package-template',
        'packages' => [
            'include' => ['*'],
            'exclude' => [
                'actions',
                'docs',
                'yii-docker',
                'yii-debug-frontend',
                'yii-gii-frontend',

                // apps
                'app',
                'app-api',
                'demo',
                'demo-api',
            ],
        ],
        'files' => [
            '.github/workflows/mutation.yml',
            'infection.json.dist',
        ],
    ],
    'static' => [
        'source' => 'package-template',
        'packages' => [
            'include' => ['*'],
            'exclude' => [
                'actions',
                'docs',
                'yii-docker',
                'yii-debug-frontend',
                'yii-gii-frontend',
            ],
        ],
        'files' => [
            '.github/workflows/static.yml',
            'psalm.xml',
        ],
    ],
    'rector' => [
        'source' => 'package-template',
        'packages' => [
            'include' => ['*'],
            'exclude' => [
                'actions',
                'docs',
                'yii-docker',
                'yii-debug-frontend',
                'yii-gii-frontend',
            ],
        ],
        'files' => [
            '.github/workflows/rector.yml',
            'rector.php',
        ],
    ],
    'composer-require-checker' => [
        'source' => 'package-template',
        'packages' => [
            'include' => ['*'],
            'exclude' => [
                'actions',
                'docs',
                'yii-docker',
                'yii-debug-frontend',
                'yii-gii-frontend',
            ],
        ],
        'files' => [
            '.github/workflows/composer-require-checker.yml',
        ],
    ],
];
