<?php

namespace Yiisoft\Dev\Commands;

use Yiisoft\Dev\Console;
use Yiisoft\Dev\Tools;

/**
 * @author Carsten Brandt <mail@cebe.cc>
 */
class InstallCommand implements CommandInterface
{
    private $package;

    public function __construct(string $package = null)
    {
        $this->package = $package;
    }

    public function run(): void
    {
        if ($this->package === null) {
            $this->installAllPackage();
        } elseif (isset(Tools::getPackageList()[$this->package])) {
            $this->installPackage($this->package);
        } else {
            Console::stdErrLn("Package '$this->package' not found in PackageStorage");
            exit(1);
        }

        Tools::relinkVendorDirectories(Tools::getInstalledPackages());
        Console::stdOutLn('done.', 32);
    }

    private function installAllPackage(): void
    {
        foreach (Tools::getPackageList() as $package => $dir) {
            $this->installPackage($package);
        }
    }

    /**
     * @param string $package
     */
    private function installPackage(string $package): void
    {
        Tools::installPackage($package);
        Tools::clearlinks($package);
        Tools::composerInstall($package);
    }
}
