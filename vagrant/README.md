Vagrant environment for yii-dev-tool
====================================

Stack
-----
 
* Ubuntu 18.04 
* PHP 7.4
* Composer with [prestissimo plugin](https://github.com/hirak/prestissimo)
* xDebug
* htop
* mc (Midnight Commander)

Installation
------------

### Manual for Linux/Unix users

1. Install [VirtualBox](https://www.virtualbox.org/wiki/Downloads)
2. Install [Vagrant](https://www.vagrantup.com/downloads.html)
3. Create GitHub [personal API token](https://github.com/blog/1509-personal-api-tokens)
4. Prepare project:
   
   ```bash
   git clone https://github.com/yiisoft/yii-dev-tool.git
   cd yii-dev-tool/vagrant/config
   cp vagrant-local.example.yml vagrant-local.yml
   ```
   
5. Place your GitHub personal API token to `vagrant-local.yml`, also specify your name and email for git commits
6. Change directory to project root:

   ```bash
   cd yii-dev-tool
   ```

7. Run command:

   ```bash
   vagrant up
   ```
   
That's all. You just need to wait for completion! 
   
### Manual for Windows users

1. Install [VirtualBox](https://www.virtualbox.org/wiki/Downloads)
2. Install [Vagrant](https://www.vagrantup.com/downloads.html)
3. Reboot
4. Create GitHub [personal API token](https://github.com/blog/1509-personal-api-tokens)
5. Prepare project:
   * download repo [yiisoft/yii-dev-tool](https://github.com/yiisoft/yii-dev-tool/archive/master.zip)
   * unzip it
   * go into directory `yii-dev-tool-master/vagrant/config`
   * copy `vagrant-local.example.yml` to `vagrant-local.yml`

6. Place your GitHub personal API token to `vagrant-local.yml`, also specify your name and email for git commits
7. Open terminal (`cmd.exe`), **change directory to project root** and run command:

   ```bash
   vagrant up
   ```
   
   (You can read [here](http://www.wikihow.com/Change-Directories-in-Command-Prompt) how to change directories in command prompt) 

That's all. You just need to wait for completion! 


Using Vagrant environment
-------------------------

Now you can login by SSH to the created virtual machine:

```bash
vagrant ssh
```

After that, you can use the commands of **yii-dev-tool** on the machine as usual. 
For example:

```bash
./yii-dev install di,rbac,yii-cycle,view
```

Problems with composer plugins
------------------------------

Sometimes some composer plugins [do not work](https://github.com/Ocramius/PackageVersions/issues/107) during virtualization.

In this case use flag `--no-plugins` when installing and updating packages. For example:

```bash
./yii-dev install --no-plugins di,rbac,yii-cycle,view
```

Update example:

```bash
./yii-dev update --no-plugins injector,cache,log,proxy
```


SSH keys
--------

Our vagrant environment automatically uses SSH keys from the host machine. Therefore, you do not need 
to separately generate keys for the guest machine. Just make sure the keys from your host machine are added 
to [your list of keys](https://github.com/settings/keys) on GitHub. This is necessary in order to access 
remote repositories via SSH protocol.


Bash history
------------

The bash history of the virtual machine is automatically saved to file `yii-dev-tool/vargant/history/.bash_history`
– this allows you to not lose history between recreations of the machine.


PHP CLI debugging using Xdebug and PHPStorm
-------------------------------------------

Our Vagrant environment contains already configured xDebug. You need to configure only your IDE.

### In host machine:

1. Go to PHPStorm `Settings > Languages & Frameworks > PHP > Servers`
2. Add server with following parameters:
   * Name: `console` (same as `serverName=` in `yii-dev-tool/debug` script)
   * Host, port: set vagrant box IP (`192.168.135.48` by default) and port `80`
   * Debugger: Xdebug
   * [v] Use path mappings
   * Map your project root in host to relative dir in guest (for example, `/Users/Alex/Projects/yii-dev-tool` to `/yii-dev-tool`)
   * Hit OK
3. Click `PHPStorm > Run > Start listening for PHP Debug Connections`
4. **Do not forget to put a breakpoint somewhere!**

### In guest machine:

1. Go to guest machine: `vagrant ssh`
2. Run some PHP script prefixed with `yii-dev-tool/debug` script. For example:
   
   ```bash
   cd /yii-dev-tool
   ./debug ./yii-dev
   ```

3. Debugger window should open in PhpStorm
4. Enjoy! :)
