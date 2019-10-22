<?php

namespace yiidev\commands;

use yiidev\components\console\Color;
use yiidev\components\package\Package;
use yiidev\components\package\PackageCommand;

class PullCommand extends PackageCommand
{
    public function run(): void
    {
        foreach ($this->getTargetPackages() as $package) {
            $this->gitPull($package);
        }

        $this->showPackageErrors();
    }

    private function printOperationHeader(Package $package): void
    {
        $this->getPrinter()
            ->stdoutln('-----------------------------------------------------------------------')
            ->stdout('Pulling package ')
            ->stdoutln($package->getId(), Color::CYAN)
            ->stdoutln('-----------------------------------------------------------------------')
            ->stdoutln();
    }

    private function gitPull(Package $package): void
    {
        $printer = $this->getPrinter();
        $executor = $this->getExecutor();

        if (!$package->isGitRepositoryCloned()) {
            if ($this->areTargetPackagesSpecifiedExplicitly() || $package->enabled()) {
                $this->printOperationHeader($package);
                $printer
                    ->stdoutln('The package repository is not cloned.', Color::YELLOW)
                    ->stdoutln('Pulling skipped.', Color::YELLOW)
                    ->stdoutln();
            }

            return;
        }

        $this->printOperationHeader($package);

        $command = 'cd ' . escapeshellarg($package->getPath()) . ' && git pull';
        $output = $executor->execute($command)->getLastOutput();

        $printer->stdoutln($output);

        if ($executor->hasErrorOccurred()) {
            $package->setError($executor->getLastOutput(), 'pulling package repository');
            $printer
                ->stdoutln()
                ->stderr('An error occurred during pulling package ', Color::LIGHT_RED)
                ->stderr($package->getId(), Color::CYAN)
                ->stderrln(' repository.', Color::LIGHT_RED)
                ->stderrln('Package pulling aborted.', Color::LIGHT_RED)
                ->stderrln();
        } else {
            $printer
                ->stdoutln('✔ Done.', Color::GREEN)
                ->stdoutln();
        }
    }
}
