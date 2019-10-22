<?php

namespace yiidev\commands;

use yiidev\components\console\Color;
use yiidev\components\package\Package;
use yiidev\components\package\PackageCommand;

class PushCommand extends PackageCommand
{
    public function run(): void
    {
        foreach ($this->getTargetPackages() as $package) {
            $this->gitPush($package);
        }

        $this->showPackageErrors();
    }

    private function printOperationHeader(Package $package): void
    {
        $this->getPrinter()
            ->stdoutln('-----------------------------------------------------------------------')
            ->stdout('Pushing package ')
            ->stdoutln($package->getId(), Color::CYAN)
            ->stdoutln('-----------------------------------------------------------------------')
            ->stdoutln();
    }

    private function gitPush(Package $package): void
    {
        $printer = $this->getPrinter();
        $executor = $this->getExecutor();

        if (!$package->isGitRepositoryCloned()) {
            if ($this->areTargetPackagesSpecifiedExplicitly() || $package->enabled()) {
                $this->printOperationHeader($package);
                $printer
                    ->stdoutln('The package repository is not cloned.', Color::YELLOW)
                    ->stdoutln('Pushing skipped.', Color::YELLOW)
                    ->stdoutln();
            }

            return;
        }

        $this->printOperationHeader($package);

        $command = 'cd ' . escapeshellarg($package->getPath()) . ' && git push';
        $output = $executor->execute($command)->getLastOutput();

        $printer->stdoutln($output);

        if ($executor->hasErrorOccurred()) {
            $package->setError($executor->getLastOutput(), 'pushing package repository');
            $printer
                ->stdoutln()
                ->stderr('An error occurred during pushing package ', Color::LIGHT_RED)
                ->stderr($package->getId(), Color::CYAN)
                ->stderrln(' repository.', Color::LIGHT_RED)
                ->stderrln('Package pushing aborted.', Color::LIGHT_RED)
                ->stderrln();
        } else {
            $printer
                ->stdoutln('✔ Done.', Color::GREEN)
                ->stdoutln();
        }
    }
}
