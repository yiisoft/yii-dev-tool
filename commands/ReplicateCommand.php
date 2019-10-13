<?php

namespace yiidev\commands;

use yiidev\components\console\Printer;
use yiidev\components\package\Package;
use yiidev\components\package\PackageList;
use yiidev\components\package\PackageManager;
use yiidev\components\package\ReplicationSource;

class ReplicateCommand
{
    /** @var Printer */
    private $printer;

    /** @var string|null */
    private $targetPackageName;

    /** @var PackageList */
    private $packageList;

    /** @var Package */
    private $replicationSource;

    public function __construct(Printer $printer, string $targetPackageName = null)
    {
        $this->printer = $printer;
        $this->targetPackageName = $targetPackageName;

        $this->packageList = new PackageList(
            __DIR__ . '/../packages.php',
            __DIR__ . '/../dev'
        );

        $replicationConfig = require __DIR__ . '/../replicate.php';

        $this->replicationSource = new ReplicationSource(
            $replicationConfig['sourcePackage'],
            $this->packageList->getPackage($replicationConfig['sourcePackage'])->getDirectoryName(),
            __DIR__ . '/../dev',
            $replicationConfig['sourceFiles']
        );
    }

    public function run(): void
    {
        $target = $this->targetPackageName;
        $list = $this->packageList;
        $manager = new PackageManager($this->printer);

        if ($target === null) {
            $manager->replicateToPackages($list, $this->replicationSource);
        } elseif ($list->hasPackage($target)) {
            $manager->replicateToPackage($list->getPackage($target), $this->replicationSource);
        } else {
            $this->printer->stderrln("Package '$target' not found in packages.php");

            exit(1);
        }
    }
}
