<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Test\Component\Composer;

use PHPUnit\Framework\TestCase;
use Yiisoft\YiiDevTool\Component\Composer\ComposerConfig;

final class ComposerConfigTest extends TestCase
{
    public function mergeProvider()
    {
        return [
            /**
             * This dataset covers the following test cases:
             * ---------------------------------------------
             * Simple merge:
             * 1. Change PHP version to "^8.0"
             * 2. Add new package "yiisoft/strings"
             * 3. Add new section "require-dev"
             * 4. Change boolean value of "sort-packages" from false to true
             *
             * Merge of arrays:
             * 1. Add new tags
             * 2. Add new author
             *
             * Merge of nested values:
             * 1. Change value of "extra" > "branch-alias"
             * 2. Change value of "extra" > "config-plugin" > "common"
             * 3. Add value for "extra" > "config-plugin" > "tests"
             */
            [
                <<<'JSON'
{
    "name": "yiisoft/some-package",
    "keywords": [
        "some",
        "package"
    ],
    "authors": [
        {
            "name": "Qiang Xue",
            "email": "qiang.xue@gmail.com",
            "homepage": "http://www.yiiframework.com/",
            "role": "Founder and project lead"
        },
        {
            "name": "Alexander Makarov",
            "email": "sam@rmcreative.ru",
            "homepage": "http://rmcreative.ru/",
            "role": "Core framework development"
        }
    ],
    "require": {
        "php": "^7.4",
        "ext-curl": "*"
    },
    "config": {
        "sort-packages": false
    },
    "extra": {
        "branch-alias": {
            "dev-master": "3.0.x-dev"
        },
        "config-plugin": {
            "params": "config/params.php",
            "common": "config/wrong-value.php"
        }
    }
}
JSON,
                <<<'JSON'
{
    "keywords": [
        "yii",
        "yii3"
    ],
    "authors": [
        {
            "name": "Carsten Brandt",
            "email": "mail@cebe.cc",
            "homepage": "http://cebe.cc/",
            "role": "Core framework development"
        }
    ],
    "require": {
        "php": "^8.0",
        "yiisoft/strings": "^3.0@dev"
    },
    "config": {
        "sort-packages": true
    },
    "extra": {
        "branch-alias": {
            "dev-master": "4.0.x-dev"
        },
        "config-plugin": {
            "common": "config/common.php",
            "tests": "config/tests.php"
        }
    },
    "require-dev": {
        "phpunit/phpunit": "^9.0",
        "phan/phan": "^3.0"
    }
}
JSON,
                <<<'JSON'
{
    "name": "yiisoft/some-package",
    "keywords": [
        "some",
        "package",
        "yii",
        "yii3"
    ],
    "authors": [
        {
            "name": "Qiang Xue",
            "email": "qiang.xue@gmail.com",
            "homepage": "http://www.yiiframework.com/",
            "role": "Founder and project lead"
        },
        {
            "name": "Alexander Makarov",
            "email": "sam@rmcreative.ru",
            "homepage": "http://rmcreative.ru/",
            "role": "Core framework development"
        },
        {
            "name": "Carsten Brandt",
            "email": "mail@cebe.cc",
            "homepage": "http://cebe.cc/",
            "role": "Core framework development"
        }
    ],
    "require": {
        "php": "^8.0",
        "ext-curl": "*",
        "yiisoft/strings": "^3.0@dev"
    },
    "config": {
        "sort-packages": true
    },
    "extra": {
        "branch-alias": {
            "dev-master": "4.0.x-dev"
        },
        "config-plugin": {
            "params": "config/params.php",
            "common": "config/common.php",
            "tests": "config/tests.php"
        }
    },
    "require-dev": {
        "phpunit/phpunit": "^9.0",
        "phan/phan": "^3.0"
    }
}
JSON,
            ],
        ];
    }

    /**
     * @param $originalJson
     * @param $additionalJson
     * @param $resultJson
     * @dataProvider mergeProvider
     */
    public function testMerge(string $originalJson, string $additionalJson, string $resultJson)
    {
        $originalConfig = ComposerConfig::createByJson($originalJson);
        $additionalConfig = ComposerConfig::createByJson($additionalJson);

        $this->assertSame($resultJson, $originalConfig->merge($additionalConfig)->asPrettyJson());
    }
}
