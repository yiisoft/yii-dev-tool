<?php

namespace yiidev\commands;

use yiidev\components\console\Color;
use yiidev\components\console\Executor;
use yiidev\components\console\Printer;
use yiidev\components\package\PackageList;
use yiidev\components\package\PackageManager;

class LintCommand
{
    /** @var Printer */
    private $printer;

    /** @var string|null */
    private $targetPackageName;

    /** @var PackageList */
    private $packageList;

    /** @var string */
    private $codeSnifferBinPath;

    public function __construct(Printer $printer, string $targetPackageName = null)
    {
        $this->printer = $printer;
        $this->targetPackageName = $targetPackageName;

        $this->packageList = new PackageList(
            __DIR__ . '/../packages.php',
            __DIR__ . '/../dev'
        );

        $this->codeSnifferBinPath = __DIR__ . '/../vendor/bin/phpcs';
    }

    public function run(): void
    {
        $target = $this->targetPackageName;
        $list = $this->packageList;
        $manager = new PackageManager($this->printer);

        $this->ensureLinterInstalled();

        if ($target === null) {
            $manager->lintAll($list, $this->codeSnifferBinPath);
        } elseif ($list->hasPackage($target)) {
            $manager->lint($list->getPackage($target), $this->codeSnifferBinPath);
        } else {
            $this->printer->stderrln("Package '$target' not found in packages.php");

            exit(1);
        }
    }

    private function ensureLinterInstalled(): void
    {
        if (file_exists($this->codeSnifferBinPath)) {
            return;
        }

        $printer = $this->printer;
        $executor = new Executor();

        $this->printer
            ->stdoutln("Linter not found.", COLOR::YELLOW)
            ->stdoutln("Installing linter...", COLOR::GREEN);

        $composerAnsiOption = $this->printer->isColorsEnabled() ? ' --ansi' : ' --no-ansi';
        $command = "composer install --prefer-dist --no-progress $composerAnsiOption";

        $output = $executor->execute($command)->getLastOutput();

        $printer
            ->stdoutln($output)
            ->stdoutln();

        if ($executor->hasErrorOccurred()) {
            $printer->stderrln('Failed to install linter.', COLOR::LIGHT_RED);

            exit(1);
        }

        $printer
            ->stdoutln('Done.', COLOR::GREEN)
            ->stdoutln();
    }
}
