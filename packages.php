<?php

$packages = [
    // repo => path

    // core packages
    'yiisoft/yii-core' => 'yii-core',
    'yiisoft/di'       => 'di',

    // console
    'yiisoft/yii-console' => 'yii-console',

    // api
    'yiisoft/yii-rest' => 'yii-rest',

    // web
    'yiisoft/yii-web'          => 'yii-web',
    'yiisoft/yii-jquery'       => 'yii-jquery',
    'yiisoft/yii-masked-input' => 'yii-masked-input ',

    // project templates
    'yiisoft/yii-base-api'         => 'yii-base-api',
    'yiisoft/yii-base-web'         => 'yii-base-web',
    'yiisoft/yii-project-template' => 'yii-project-template',

    // other
    'yiisoft/yii-docker' => 'yii-docker',
];

if (file_exists($localFile = __DIR__.'/packages.local.php')) {
    $packages = array_merge($packages, require $localFile);
}

return $packages;
