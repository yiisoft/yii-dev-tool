<?php
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
                'docs',
                'yii-docker',
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
                'docs',
                'yii-docker',
                'access',
                // apps (they have to report coverage from Codeception)
                'app',
                'app-api',
                'yii-demo',
            ],
        ],
        'files' => [
            '.scrutinizer.yml'
        ],
    ],
    'styleci' => [
        'source' => 'package-template',
        'packages' => [
            'include' => ['*'],
            'exclude' => [
                'docs',
                'yii-docker',
                'requirements'
            ],
        ],
        'files' => [
            '.styleci.yml'
        ],
    ],
    'bc_' => [
        'source' => 'package-template',
        'packages' => [
            'include' => ['*'],
            'exclude' => [
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
                'yii-demo',
            ],
        ],
        'files' => [
            '.github/workflows/bc.yml',
        ],
    ],
    'build' => [
        'source' => 'package-template',
        'packages' => [
            'include' => ['*'],
            'exclude' => [
                'docs',
                'yii-docker',
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
                'docs',
                'yii-docker',

                // apps
                'app',
                'app-api',
                'yii-demo',
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
                'docs',
                'yii-docker',
            ],
        ],
        'files' => [
            '.github/workflows/static.yml',
            'psalm.xml',
        ],
    ],
];
