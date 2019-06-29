<?php

namespace Yiisoft\Dev\Commands;

use Yiisoft\Dev\Console;
use Yiisoft\Dev\Tools;

class StatusCommand implements CommandInterface
{
    private $package;

    public function __construct(string $package = null)
    {
        $this->package = $package;
    }

    public function run(): void
    {
        if ($this->package === null) {
            $this->printStatusAllPackage();
        } elseif (isset(Tools::getPackageList()[$this->package])) {
            $this->printStatusPackage($this->package);
        } else {
            Console::stdErrLn('Package ' . $this->package . ' not found in packages.php');
            exit(1);
        }
    }

    private function printStatusAllPackage(): void
    {
        foreach (Tools::getPackageList() as $package => $dir) {
            $this->printStatusPackage($package);
        }
    }

    private function printStatusPackage($package): void
    {
        Tools::gitPackageStatus($package);
    }
}
