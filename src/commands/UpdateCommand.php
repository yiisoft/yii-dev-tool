<?php

namespace Yiisoft\Dev\Commands;

use Yiisoft\Dev\Console;
use Yiisoft\Dev\Tools;

/**
 * @author Carsten Brandt <mail@cebe.cc>
 */
class UpdateCommand implements CommandInterface
{
    private $package;

    public function __construct(string $package = null)
    {
        $this->package = $package;
    }

    public function run(): void
    {
        if ($this->package === null) {
            $this->updateAllPackage();
        } elseif (isset(Tools::getPackageList()[$this->package])) {
            $this->updatePackage($this->package);
        } else {
            Console::stdErrLn("Package '$this->package' not found in packages.php");
            exit(1);
        }

        Tools::relinkVendorDirectories(Tools::getInstalledPackages());
        Console::stdOutLn('done.', 32);
    }

    private function updateAllPackage(): void
    {
        foreach (Tools::getPackageList() as $package => $dir) {
            $this->updatePackage($package);
        }
    }

    private function updatePackage($package): void
    {
        Tools::installPackage($package);
        Tools::clearLinks($package);
        Tools::gitComposerUpdate($package);
    }
}
