<?php

namespace Yiisoft\Dev\Commands;

use Yiisoft\Dev\Console;
use Yiisoft\Dev\PackageStorage;
use Yiisoft\Dev\Tools;

class ReplicateCommand implements CommandInterface
{
    private $package;
    private $sourcePackage;
    private $sourcePath;
    private $sourceFiles;

    public function __construct(string $package = null)
    {
        $this->package = $package;
        $replicate = (new PackageStorage())->replicateConfig;
        $this->sourcePackage = $replicate['sourcePackage'];
        $this->sourcePath = Tools::getPackageDir(Tools::getPackageList()[$this->sourcePackage]);
        $this->sourceFiles = $replicate['sourceFiles'];
    }

    public function run(): void
    {
        if ($this->package === null) {
            $this->replicateAllPackage();
        } elseif (isset($packages[$this->package])) {
            $this->replicatePackage($this->package);
        } else {
            Console::stdErrLn("Package '$this->package' not found in packages.php");
            exit(1);
        }
    }

    private function replicateAllPackage(): void
    {
        foreach (Tools::getPackageList() as $package => $dir) {
            $this->replicatePackage($package);
        }
    }

    private function replicatePackage($package): void
    {
        if ($package === $this->sourcePackage) {
            return;
        }
        Tools::replicatePackage($package, $this->sourcePath, $this->sourceFiles);
    }
}
