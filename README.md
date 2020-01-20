Yii 3 development environment
=============================

This repo contains tools to set up a development environment for Yii 3 packages.
It allows to work on separate packages and test the result in other packages at the same time.


Requirements
------------

### Manual install

If you prefer manual install, you need:
- GNU/Linux or Windows **with bash**
- PHP 7.4 or higher
- [Composer](https://getcomposer.org/) installed and
  available [as `composer` on the command line](https://getcomposer.org/doc/00-intro.md#globally)

### Vagrant

If you prefer Vagrant, you only need to install 
[Vagrant](https://www.vagrantup.com/downloads.html) and [VirtualBox](https://www.virtualbox.org/wiki/Downloads),
because our Vagrant environment already contains Ubuntu, PHP and Composer. 
See our Vagrant [documentation](vagrant/README.md) for details.

### Docker

If you prefer Docker, you only need to install [Docker](https://www.docker.com/).


Manual install
--------------

```bash
git clone https://github.com/yiisoft/yii-dev-tool
cd yii-dev-tool
composer install
./yii-dev install
```
    
The above command will clone all Yii 3 packages and run `composer install` in them.
You may select packages by providing a second argument:

```bash
./yii-dev install di,rbac,yii-cycle,view
```
    
> Tip: You can speed up Composer significantly by installing [prestissimo](https://github.com/hirak/prestissimo) plugin
> globally.

> Note: In case you are using PhpStorm you have to add `yiisoft` exclusion pattern in "Settings → Directories → Exclude Files".
> Else it would go into infinite indexing cycle.


Upgrade
-------

To upgrade **yii-dev-tool** to the latest version, run the following commands:

```bash
cd yii-dev-tool
git pull
composer update
```


Configuration
-------------

To customize the configuration of **yii-dev-tool**, create your local configuration `packages.local.php` 
using file `packages.local.php.example` as example. In this file you will find examples of all available 
configuration options.


Docker
------

If you want to run **yii-dev-tool** in a Docker container, run the following command:

```bash
docker-compose run --rm php bash
```

Afterwards you can run the above commands like `./yii-dev install`.


Usage example
-------------

### Objective

Suppose we want to work on three interdependent packages:
* yiisoft/yii-demo
* yiisoft/view
* yiisoft/i18n

Package **yii-demo** depends on package **view**, and package **view** depends on package **i18n**.

Suppose we want to add new features to package **i18n**, and then use them in package **view**. 
After that, we will need to run the tests in package **yii-demo** and make sure that everything works correctly.

### Step 1: create forks

Go to the page of each repository and click the "Fork" button:
* [yiisoft/yii-demo](https://github.com/yiisoft/yii-demo)
* [yiisoft/view](https://github.com/yiisoft/view)
* [yiisoft/i18n](https://github.com/yiisoft/i18n)

Suppose my nickname on Github is "samdark". Then I will get three forks:
* samdark/yii-demo
* samdark/view
* samdark/i18n

For your nickname you will get other fork names.

### Step 2: install yii-dev-tool

Now install **yii-dev-tool**:

```bash
git clone https://github.com/yiisoft/yii-dev-tool
cd yii-dev-tool
composer install
```
        
### Step 3: configure yii-dev-tool to use forks

In order for **yii-dev-tool** to use our forks, they must be configured. 
Create your configuration:

```bash
cd yii-dev-tool
cp packages.local.php.example packages.local.php
```

Specify the forks in config `packages.local.php`:

```php
$packages = [
    'yii-demo' => 'samdark/yii-demo',
    'view' => 'samdark/view',
    'i18n' => 'samdark/i18n',
];
```

### Step 4: install packages

Now install the packages:

```bash
cd yii-dev-tool
./yii-dev install yii-demo,view,i18n
```

This command clones the fork repositories from GitHub to the local directory `yii-dev-tool/dev/`, 
[sets upstream](https://help.github.com/en/github/collaborating-with-issues-and-pull-requests/configuring-a-remote-for-a-fork) 
for them and executes `composer install` in each package. Then symlinks will be created:
* yii-dev-tool/dev/yii-demo/vendor/yiisoft/view -> yii-dev-tool/dev/view
* yii-dev-tool/dev/view/vendor/yiisoft/i18n -> yii-dev-tool/dev/i18n

Due to these symlinks, changes in packages will immediately affect the operation of packages that depend on them.
It is very convenient for development.

### Step 5: create a git branch for work

Create a new **feature-x** branch in the repositories:

```bash
cd yii-dev-tool
./yii-dev git/checkout-branch feature-x yii-demo,view,i18n
```

### Step 6: writing the code

Now make the necessary changes to the code of package **i18n** in folder `yii-dev-tool/dev/i18n`. 
Next, make changes to the code of package **view** in folder `yii-dev-tool/dev/view`. 
And, finally, change package **yii-demo** in folder `yii-dev-tool/dev/yii-demo`.

### Step 7: run the tests

Make sure the tests pass. For instance, package **yii-demo** tests can be run with the following command:

```bash
cd yii-dev-tool/dev/yii-demo
./vendor/bin/phpunit
```

### Step 8: commit and push the changes to the fork repositories

Commit the changes:

```bash
cd yii-dev-tool
./yii-dev git/commit "Add feature X" yii-demo,view,i18n
```

Push the new code to remote repositories:

```bash
cd yii-dev-tool
./yii-dev git/push yii-demo,view,i18n
```

### Step 9: create pull requests

Go to the pages of the original repositories and create a PR in each:
* [yiisoft/yii-demo](https://github.com/yiisoft/yii-demo)
* [yiisoft/view](https://github.com/yiisoft/view)
* [yiisoft/i18n](https://github.com/yiisoft/i18n)

### Final notes

That's all. We developed new functionality and submitted it for review 🙂 Of course, the steps will be 
slightly different for different tasks and configurations. 

Remember that **yii-dev-tool** contains many other commands for working with repositories:

* `./yii-dev exec` – executes the specified console command in each package
* `./yii-dev git/checkout-branch` – creates, if not exists, and checkout a git branch
* `./yii-dev git/commit` – add and commit changes into each package repository
* `./yii-dev git/pull` – pull changes from package repositories
* `./yii-dev git/push` – push changes into package repositories
* `./yii-dev git/status` – show git status of packages
* `./yii-dev install` – install packages
* `./yii-dev lint` – check packages according to PSR12 standard
* `./yii-dev replicate/files` – copy files specified in `config/replicate/files.php` into each package
* `./yii-dev replicate/composer-config` – merge `config/replicate/composer.json` into `composer.json` of each package
* `./yii-dev update` – update packages

If you encounter any problems, [create an issue](https://github.com/yiisoft/yii-dev-tool/issues/new) – 
and we'll try to help you.
