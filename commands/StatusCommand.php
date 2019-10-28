<?php

namespace yiidev\commands;

use yiidev\components\console\Color;
use yiidev\components\package\Package;
use yiidev\components\package\PackageCommand;

class StatusCommand extends PackageCommand
{
    public function run(): void
    {
        foreach ($this->getTargetPackages() as $package) {
            $this->showGitStatus($package);
        }
    }

    private function printOperationHeader(Package $package): void
    {
        $this->getPrinter()
            ->stdoutln('-----------------------------------------------------------------------')
            ->stdout('Git status of package ')
            ->stdoutln($package->getId(), Color::CYAN)
            ->stdoutln('-----------------------------------------------------------------------')
            ->stdoutln();
    }

    private function showGitStatus(Package $package): void
    {
        $printer = $this->getPrinter();
        $executor = $this->getExecutor();

        if (!$package->isGitRepositoryCloned()) {
            if ($this->areTargetPackagesSpecifiedExplicitly()) {
                $this->printOperationHeader($package);
                $printer
                    ->stdoutln('The package repository is not cloned.', Color::YELLOW)
                    ->stdoutln('Git status check skipped.', Color::YELLOW)
                    ->stdoutln();
            }

            return;
        }

        $this->printOperationHeader($package);

        $command = 'cd ' . escapeshellarg($package->getPath()) . ' && git status -s';
        $output = $executor->execute($command)->getLastOutput();

        if (empty($output)) {
            $printer
                ->stdoutln('✔ nothing to commit, working tree clean', Color::GREEN)
                ->stdoutln();
        } else {
            $printer
                ->stdoutln($output)
                ->stdoutln();
        }
    }
}
