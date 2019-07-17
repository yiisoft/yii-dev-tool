<?php

$packages = [
    // repo => path

    'yiisoft/docs' => 'docs',

    // core packages
    'yiisoft/yii-core' => 'yii-core',
    'yiisoft/di' => 'di',
    'yiisoft/injector' => 'injector',
    'yiisoft/factory' => 'factory',
    'yiisoft/access' => 'access',
    'yiisoft/event-dispatcher' => 'event-dispatcher',
    'yiisoft/security' => 'security',
    'yiisoft/data' => 'data',
    'yiisoft/profiler' => 'profiler',
    'yiisoft/aliases' => 'aliases',
    'yiisoft/serializer' => 'serializer',

    // cache
    'yiisoft/cache' => 'cache',
    'yiisoft/cache-apc' => 'cache-apc',
    'yiisoft/cache-wincache' => 'cache-wincache',
    'yiisoft/cache-file' => 'cache-file',
    'yiisoft/cache-db' => 'cache-db',

    // RBAC
    'yiisoft/rbac' => 'rbac',
    'yiisoft/rbac-db' => 'rbac-db',
    'yiisoft/rbac-php' => 'rbac-php',

    // log
    'yiisoft/log' => 'log',
    'yiisoft/log-target-db' => 'log-target-db',
    'yiisoft/log-target-email' => 'log-target-email',
    'yiisoft/log-target-file' => 'log-target-file',
    'yiisoft/log-target-syslog' => 'log-target-syslog',

    // i18n
    'yiisoft/i18n' => 'i18n',
    'yiisoft/i18n-message-php' => 'i18n-message-php',
    'yiisoft/i18n-message-gettext' => 'i18n-message-gettext',
    'yiisoft/i18n-formatter-intl' => 'i18n-formatter-intl',

    // queue
    'yiisoft/yii-queue' => 'yii-queue',
    'yiisoft/yii-queue-interop' => 'yii-queue-interop',

    // mutex
    'yiisoft/mutex' => 'mutex',
    'yiisoft/mutex-file' => 'mutex-file',
    'yiisoft/mutex-db-pgsql' => 'mutex-db-pgsql',
    'yiisoft/mutex-db-oracle' => 'mutex-db-oracle',
    'yiisoft/mutex-db-mysql' => 'mutex-db-mysql',

    // mailer
    'yiisoft/mailer' => 'mailer',
    'yiisoft/mailer-swiftmailer' => 'mailer-swiftmailer',

    //filesystem

    // helpers
    'yiisoft/arrays' => 'arrays',
    'yiisoft/strings' => 'strings',
    'yiisoft/var-dumper' => 'vardumper',

    // console
    'yiisoft/yii-console' => 'yii-console',

    // api
    'yiisoft/yii-rest' => 'yii-rest',

    // db
    'yiisoft/db' => 'db',
    'yiisoft/db-mysql' => 'db-mysql',
    'yiisoft/db-pgsql' => 'db-pgsql',
    'yiisoft/db-sqlite' => 'db-sqlite',
    'yiisoft/db-mssql' => 'db-mssql',
    'yiisoft/db-oracle' => 'db-oracle',
    'yiisoft/db-mongodb' => 'db-mongodb',
    'yiisoft/active-record' => 'active-record',
    //'yiisoft/migration' => 'migration',

    // router
    'yiisoft/router' => 'router',
    'yiisoft/router-fastroute' => 'router-fastroute',

    // web
    'yiisoft/yii-web' => 'yii-web',
    'yiisoft/view' => 'view',
    'yiisoft/yii-jquery' => 'yii-jquery',
    'yiisoft/yii-masked-input' => 'yii-masked-input ',
    'yiisoft/yii-dataview' => 'yii-dataview',

    'yiisoft/yii-bootstrap3' => 'yii-bootstrap3',
    'yiisoft/yii-captcha' => 'yii-captcha',

    'yiisoft/yii-auth-client' => 'yii-auth-client',

    // project templates
    'yiisoft/yii-base-api' => 'yii-base-api',
    'yiisoft/yii-base-web' => 'yii-base-web',
    'yiisoft/yii-project-template' => 'yii-project-template',

    // demo
    'yiisoft/yii-demo' => 'yii-demo',

    // other
    'yiisoft/yii-docker' => 'yii-docker',

    // repository template
    'yiisoft/template' => 'template',
];

if (file_exists($localFile = __DIR__ . '/packages.local.php')) {
    $packages = array_merge($packages, require $localFile);
}

return $packages;
