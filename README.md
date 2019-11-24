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

```bash
git clone https://github.com/yiisoft/yii-dev-tool
cd yii-dev-tool
composer install
./yii-dev install
```
    
The above command will clone all Yii 3 packages and run `composer install` in them.
You may select packages by providing a second argument:

```bash
./yii-dev install di,rbac,db-mysql,view
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
* yiisoft/yii-base-api
* yiisoft/active-record
* yiisoft/db

Package **yii-base-api** depends on package **active-record**, and package **active-record** depends on package **db**.

Suppose we want to add new features to package **db**, and then use them in package **active-record**. 
After that, we will need to run the tests in package **yii-base-api** and make sure that everything works correctly.

### Step 1: create forks

Go to the page of each repository and click the "Fork" button:
* [yiisoft/yii-base-api](https://github.com/yiisoft/yii-base-api)
* [yiisoft/active-record](https://github.com/yiisoft/active-record)
* [yiisoft/db](https://github.com/yiisoft/db)

Suppose my nickname on Github is "samdark". Then I will get three forks:
* samdark/yii-base-api
* samdark/active-record
* samdark/db

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
    'yii-base-api' => 'samdark/yii-base-api',
    'active-record' => 'samdark/active-record',
    'db' => 'samdark/db',
];
```

### Step 4: install packages

Now install the packages:

```bash
cd yii-dev-tool
./yii-dev install yii-base-api,active-record,db
```

This command clones the fork repositories from GitHub to the local directory `yii-dev-tool/dev/`, 
[sets upstream](https://help.github.com/en/github/collaborating-with-issues-and-pull-requests/configuring-a-remote-for-a-fork) 
for them and executes `composer install` in each package. Then symlinks will be created:
* yii-dev-tool/dev/yii-base-api/vendor/yiisoft/active-record -> yii-dev-tool/dev/active-record
* yii-dev-tool/dev/active-record/vendor/yiisoft/db -> yii-dev-tool/dev/db

Due to these symlinks, changes in packages will immediately affect the operation of packages that depend on them.
It is very convenient for development.

### Step 5: create a git branch for work

Create a new **feature-x** branch in the repositories:

```bash
cd yii-dev-tool
./yii-dev checkout-branch feature-x yii-base-api,active-record,db
```

### Step 6: writing the code

Now make the necessary changes to the code of package **db** in folder `yii-dev-tool/dev/db`. 
Next, make changes to the code of package **active-record** in folder `yii-dev-tool/dev/active-record`. 
And, finally, change package **yii-base-api** in folder `yii-dev-tool/dev/yii-base-api`.

### Step 7: run the tests

Make sure the tests pass. For instance, package **yii-base-api** tests can be run with the following command:

```bash
cd yii-dev-tool/dev/yii-base-api
./vendor/bin/phpunit
```

### Step 8: commit and push the changes to the fork repositories

Commit the changes:

```bash
cd yii-dev-tool
./yii-dev commit "Add feature X" yii-base-api,active-record,db
```

Push the new code to remote repositories:

```bash
cd yii-dev-tool
./yii-dev push yii-base-api,active-record,db
```

### Step 9: create pull requests

Go to the pages of the original repositories and create a PR in each:
* [yiisoft/yii-base-api](https://github.com/yiisoft/yii-base-api)
* [yiisoft/active-record](https://github.com/yiisoft/active-record)
* [yiisoft/db](https://github.com/yiisoft/db)

### Final notes

That's all. We developed new functionality and submitted it for review 🙂 Of course, the steps will be 
slightly different for different tasks and configurations. 

Remember that **yii-dev-tool** contains many other commands for working with repositories:

* `./yii-dev checkout-branch` – creates, if not exists, and checkout a git branch
* `./yii-dev commit` – add and commit changes into each package repository
* `./yii-dev install` – install packages
* `./yii-dev lint` – check packages according to PSR12 standard
* `./yii-dev pull` – pull changes from package repositories
* `./yii-dev push` – push changes into package repositories
* `./yii-dev replicate` – copy files specified in `replicate.php` into each package
* `./yii-dev status` – show git status of packages
* `./yii-dev update` – update packages

If you encounter any problems, [create an issue](https://github.com/yiisoft/yii-dev-tool/issues/new) – 
and we'll try to help you.
