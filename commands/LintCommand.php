<?php

namespace yiidev\commands;

use yiidev\components\console\Color;
use yiidev\components\console\Printer;
use yiidev\components\package\Package;
use yiidev\components\package\PackageCommand;

class LintCommand extends PackageCommand
{
    /** @var string */
    private $codeSnifferBinPath;

    public function __construct(Printer $printer, string $commaSeparatedPackageIds = null)
    {
        parent::__construct($printer, $commaSeparatedPackageIds);

        $this->codeSnifferBinPath = __DIR__ . '/../vendor/bin/phpcs';
    }

    public function run(): void
    {
        $this->ensureLinterInstalled();

        foreach ($this->getTargetPackages() as $package) {
            $this->lint($package, $this->codeSnifferBinPath);
        }
    }

    private function ensureLinterInstalled(): void
    {
        if (file_exists($this->codeSnifferBinPath)) {
            return;
        }

        $printer = $this->getPrinter();
        $executor = $this->getExecutor();

        $printer
            ->stdoutln('Linter not found.', COLOR::YELLOW)
            ->stdoutln('Installing linter...');

        $composerAnsiOption = $printer->isColorsEnabled() ? ' --ansi' : ' --no-ansi';
        $command = "composer install --prefer-dist --no-progress $composerAnsiOption";

        $output = $executor->execute($command)->getLastOutput();

        $printer->stdoutln($output);

        if ($executor->hasErrorOccurred()) {
            $printer
                ->stderrln('Failed to install linter.', COLOR::LIGHT_RED)
                ->stderrln('Linting aborted.', COLOR::LIGHT_RED)
                ->stderrln();

            exit(1);
        }

        $printer
            ->stdoutln('✔ Done.', Color::GREEN)
            ->stdoutln();
    }

    private function printOperationHeader(Package $package): void
    {
        $this->getPrinter()
            ->stdoutln('-----------------------------------------------------------------------')
            ->stdout('Linting package ')
            ->stdoutln($package->getId(), Color::CYAN)
            ->stdoutln('-----------------------------------------------------------------------')
            ->stdoutln();
    }

    private function lint(Package $package, string $codeSnifferBinPath): void
    {
        $printer = $this->getPrinter();
        $executor = $this->getExecutor();

        if (!$package->isGitRepositoryCloned()) {
            if ($this->areTargetPackagesSpecifiedExplicitly()) {
                $this->printOperationHeader($package);
                $printer
                    ->stdoutln('The package repository is not cloned.', Color::YELLOW)
                    ->stdoutln('Linting skipped.', Color::YELLOW)
                    ->stdoutln();
            }

            return;
        }

        $this->printOperationHeader($package);

        $command =
            $codeSnifferBinPath . ' ' .
            escapeshellarg($package->getPath()) . ' ' .
            ($printer->isColorsEnabled() ? '--colors ' : '') .
            '--standard=PSR12 --ignore=*/vendor/*,*/docs/*';

        $output = $executor->execute($command)->getLastOutput();

        // CodeSniffer exits with an error code if it finds problems
        if ($executor->hasErrorOccurred()) {
            $printer
                ->stdoutln($output)
                ->stdoutln();
        } else {
            $printer
                ->stdoutln('No problems found ✔', Color::GREEN)
                ->stdoutln();
        }
    }
}
