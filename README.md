Yii 3 development environment
=============================

This repo contains tools to set up a development environment for Yii 3 packages.

It allows to work on separate packages and test the result in other packages at the same time.


Requirements
------------

- This is currently only tested on GNU/Linux and Windows with bash, if you have another system, please try and report if something is not working.
- PHP 7.2 or higher
- [Composer](https://getcomposer.org/) installed and
  available [as `composer` on the command line](https://getcomposer.org/doc/00-intro.md#globally)

Install
-------

    git clone https://github.com/yiisoft/yii-dev-tool
    cd yii-dev-tool
    ./yii-dev install
    
The above command will clone all Yii 3 packages and run `composer install` in them.
You may select packages by providing a second argument:

    ./yii-dev install di,rbac,db-mysql,view
    ...
    
> Tip: You can speed up Composer significantly by installing [prestissimo](https://github.com/hirak/prestissimo) plugin
> globally.

> Note: In case you are using PhpStorm you have to add `yiisoft` exclusion pattern in "Settings → Directories → Exclude Files".
> Else it would go into infinite indexing cycle.

Using your own fork
-------------------

In order to use your own fork copy `packages.local.php.example` to `packages.local.php` and customize it to your needs.

Status
------

In order to show git status for all packages, run the following command:

    ./yii-dev status

You can specify which packages status to display:

    ./yii-dev status yii-demo,rbac,factory,di

### Docker

Start a shell in a container

    docker-compose run --rm php bash

Afterwards you can run the above commands like `yii-dev install`.
