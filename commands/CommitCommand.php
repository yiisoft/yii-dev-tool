<?php

namespace yiidev\commands;

use yiidev\components\console\Color;
use yiidev\components\console\Printer;
use yiidev\components\package\Package;
use yiidev\components\package\PackageCommand;

class CommitCommand extends PackageCommand
{
    /** @var string */
    private $message;

    public function __construct(Printer $printer, string $message = null, string $commaSeparatedPackageIds = null)
    {
        parent::__construct($printer, $commaSeparatedPackageIds);

        if ($message === null) {
            $printer->stderrln('Message is required.');

            exit(1);
        }

        $this->message = $message;
    }

    public function run(): void
    {
        foreach ($this->getTargetPackages() as $package) {
            $this->gitCommit($package, $this->message);
        }

        $this->showPackageErrors();
    }

    private function printOperationHeader(Package $package): void
    {
        $this->getPrinter()
            ->stdoutln('-----------------------------------------------------------------------')
            ->stdout('Committing repository ')
            ->stdoutln($package->getId(), Color::CYAN)
            ->stdoutln('-----------------------------------------------------------------------')
            ->stdoutln();
    }

    private function gitCommit(Package $package, string $commitMessage): void
    {
        $printer = $this->getPrinter();
        $executor = $this->getExecutor();

        if (!$package->isGitRepositoryCloned()) {
            if ($package->enabled()) {
                $this->printOperationHeader($package);

                $package->setError('The package repository is not cloned.', 'committing package repository');

                $printer
                    ->stderr('An error occurred during committing package ', Color::LIGHT_RED)
                    ->stderr($package->getId(), Color::CYAN)
                    ->stderrln(' repository.', Color::LIGHT_RED)
                    ->stderrln('The package repository is not cloned.', Color::LIGHT_RED)
                    ->stderrln('Package committing aborted.', Color::LIGHT_RED)
                    ->stderrln();
            }

            return;
        }

        $this->printOperationHeader($package);

        $printer
            ->stdout('Committing package ')
            ->stdout($package->getId(), Color::CYAN)
            ->stdoutln('...');

        $command = 'cd ' . escapeshellarg($package->getPath()) . ' && git add . && git diff-index --quiet HEAD';
        $addResult = $executor->execute($command)->getLastResult();

        if (!$addResult) {
            $printer
                ->stdoutln('Nothing to commit.', Color::YELLOW)
                ->stdoutln('Committing skipped.', Color::YELLOW)
                ->stdoutln();

            return;
        }

        $command =
            'cd ' . escapeshellarg($package->getPath()) .
            ' && git commit -m ' . escapeshellarg($commitMessage);

        $output = $executor->execute($command)->getLastOutput();

        $printer->stdoutln($output);

        if ($executor->hasErrorOccurred()) {
            $package->setError($executor->getLastOutput(), 'committing package repository');

            $printer
                ->stdoutln()
                ->stderr('An error occurred during committing package ', Color::LIGHT_RED)
                ->stderr($package->getId(), Color::CYAN)
                ->stderrln(' repository.', Color::LIGHT_RED)
                ->stderrln('Package committing aborted.', Color::LIGHT_RED)
                ->stderrln();
        } else {
            $printer
                ->stdoutln('✔ Done.', Color::GREEN)
                ->stdoutln();
        }
    }
}
