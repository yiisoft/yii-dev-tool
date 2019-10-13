<?php

namespace yiidev\commands;

use yiidev\components\console\Printer;
use yiidev\components\package\PackageList;
use yiidev\components\package\PackageManager;

class CommitCommand
{
    /** @var Printer */
    private $printer;

    /** @var string|null */
    private $targetPackageName;

    /** @var PackageList */
    private $packageList;

    /** @var string */
    private $message;

    public function __construct(Printer $printer, string $message = null, string $targetPackageName = null)
    {
        if ($message === null) {
            $printer->stderrln('Message is required.');

            exit(1);
        }

        $this->printer = $printer;
        $this->message = $message;
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
            $manager->gitCommitAll($list, $this->message);
        } elseif ($list->hasPackage($target)) {
            $manager->gitCommit($list->getPackage($target), $this->message);
        } else {
            $this->printer->stderrln("Package '$target' not found in packages.php");

            exit(1);
        }
    }
}
