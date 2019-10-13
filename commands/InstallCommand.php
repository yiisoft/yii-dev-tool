<?php

namespace yiidev\commands;

use yiidev\components\console\Printer;
use yiidev\components\package\PackageList;
use yiidev\components\package\PackageManager;

class InstallCommand
{
    /** @var Printer */
    private $printer;

    /** @var string|null */
    private $targetPackageName;

    /** @var PackageList */
    private $packageList;

    /** @var bool */
    private $useHttp;

    public function __construct(Printer $printer, bool $useHttp, string $targetPackageName = null)
    {
        $this->printer = $printer;
        $this->useHttp = $useHttp;
        $this->targetPackageName = $targetPackageName;

        $this->packageList = new PackageList(
            __DIR__ . '/../packages.php',
            __DIR__ . '/../dev'
        );
    }

    public function run(): void
    {
        $target = $this->targetPackageName;
        $list = $this->packageList;
        $manager = new PackageManager($this->printer);

        if ($target === null) {
            $manager->installAll($list, $this->useHttp);
        } elseif ($list->hasPackage($target)) {
            $manager->install($list->getPackage($target), $this->useHttp);
        } else {
            $this->printer->stderrln("Package '$target' not found in packages.php");

            exit(1);
        }

        $manager->createSymbolicLinks($list);
        $manager->showPackageErrors($list);
    }
}
