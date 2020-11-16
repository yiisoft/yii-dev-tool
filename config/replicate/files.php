<?php

// replication config
return [
    'sourcePackage' => 'package-template',
    'sourceFiles' => [
        '.github/ISSUE_TEMPLATE.md',
        '.github/PULL_REQUEST_TEMPLATE.md',
        '.github/FUNDING.yml',
        '.github/CONTRIBUTING.md',
        '.github/CODE_OF_CONDUCT.md',
        '.github/SECURITY.md',
        '.github/workflows/build.yml',

        '.phan/config.php',

        '.editorconfig',
        '.scrutinizer.yml',
        '.styleci.yml',
        '.gitattributes',
    ],
];
