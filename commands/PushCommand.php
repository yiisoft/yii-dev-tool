<?php

namespace yiidev\commands;

use yiidev\components\console\Printer;
use yiidev\components\package\PackageList;
use yiidev\components\package\PackageManager;

class PushCommand
{
    /** @var Printer */
    private $printer;

    /** @var string|null */
    private $targetPackageName;

    /** @var PackageList */
    private $packageList;

    public function __construct(Printer $printer, string $targetPackageName = null)
    {
        $this->printer = $printer;
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
            $manager->gitPushAll($list);
        } elseif ($list->hasPackage($target)) {
            $manager->gitPush($list->getPackage($target));
        } else {
            $this->printer->stderrln("Package '$target' not found in packages.php");

            exit(1);
        }
    }
}
