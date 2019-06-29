<?php

namespace Yiisoft\Dev\Commands;

use Yiisoft\Dev\Console;
use Yiisoft\Dev\Tools;

class PushCommand implements CommandInterface
{
    private $package;

    public function __construct(string $package = null)
    {
        $this->package = $package;
    }

    public function run(): void
    {
        if ($this->package === null) {
            $this->pushAllPackage();
        } elseif (isset(Tools::getPackageList()[$this->package])) {
            $this->pushPackage($this->package);
        } else {
            Console::stdErrLn('Package ' . $this->package . ' not found in packages.php');
            exit(1);
        }
    }

    private function pushAllPackage(): void
    {
        foreach (Tools::getPackageList() as $package => $dir) {
            $this->pushPackage($package);
        }
    }

    private function pushPackage($package): void
    {
        Tools::gitPushPackage($package);
    }
}
