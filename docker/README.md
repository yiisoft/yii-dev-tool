Docker environment for yii-dev-tool
===================================

Stack
-----
 
* PHP 8.2
* Composer 2
* ~~xDebug~~
* htop
* mc (Midnight Commander)

Installation
------------

### Manual for Linux/Unix users

1. Install [Docker](https://www.docker.com/) and Docker-compose
2. Install GNU make (`apt install make`)
3. Prepare project:
   
   ```bash
   git clone https://github.com/yiisoft/yii-dev-tool.git
   cd yii-dev-tool
   make bash
   ```
   
That's all. You just need to wait for completion!

After completion you are inside
   
### Manual for Windows users

TBD

Using Docker environment
------------------------

Now you can run a bash shell in the created container:

```bash
make bash
```

After that, you can use the commands of **yii-dev-tool** on the machine as usual. 
For example:

```bash
./yii-dev install di,rbac,yii-cycle,view
```

SSH keys
--------

If your ssh keys are located in `~/.ssh` the docker environment automatically uses the SSH keys from the host machine.
Therefore, you do not need to separately generate keys for the guest machine. Just make sure the keys from your host machine are added 
to [your list of keys](https://github.com/settings/keys) on GitHub. This is necessary in order to access 
remote repositories via SSH protocol.

If your ssh keys are not located in `~/.ssh` you need to adjust the path in `docker-compose.override.yml`.

Bash history
------------

The bash history of the container is automatically saved to file `yii-dev-tool/docker/home/.bash_history`
– this allows you to not lose history between recreations of the container.

PHP Version
-----------

By default the container is using PHP 8.2. If you want to change the PHP version you can do the following:

```bash
make docker-down
# Change version of PHP in docker/Dockerfile line 1
make bash
```

This will re-build the container with the new PHP version.


PHP CLI debugging using Xdebug and PHPStorm
-------------------------------------------

TBD (not implemented yet)
