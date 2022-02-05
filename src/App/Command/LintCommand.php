<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command;

use Symfony\Component\Process\Process;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;

final class LintCommand extends PackageCommand
{
    protected function configure()
    {
        $this
            ->setName('lint')
            ->setDescription('Check packages according to PSR-12 standard');

        parent::configure();
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<success>✔ No problems found</success>';
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, "Linting package {package}");

        $process = new Process([
            './vendor/bin/phpcs',
            $package->getPath(),
            $io->hasColorSupport() ? '--colors' : '--no-colors',
            '--standard=PSR12',
            '--ignore=*/vendor/*,*/docs/*',
         ], $this->getAppRootDir());

        $process->run();

        if ($process->getExitCode() > 0) {
            $io->important()->info($process->getOutput() . $process->getErrorOutput());
        } else {
            $io->success('✔ No problems found.');
        }
    }
}
