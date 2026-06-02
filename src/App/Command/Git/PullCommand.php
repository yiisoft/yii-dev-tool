<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Git;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Process\Process;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Console\ProcessOutput;
use Yiisoft\YiiDevTool\App\Component\Package\Package;

#[AsCommand(
    name: 'git:pull',
    description: 'Pull changes from package repositories'
)]
final class PullCommand extends PackageCommand
{
    protected function configure(): void
    {
        $this->setAliases(['pull']);

        parent::configure();
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<em>Nothing to pull</em>';
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, 'Pulling package {package}');

        $process = new Process(['git', 'pull'], $package->getPath());
        $process->setTimeout(null);
        ProcessOutput::run($process, $io);

        if ($process->isSuccessful()) {
            $io->done();
        } else {
            $output = $process->getErrorOutput();

            $io->error([
                "An error occurred during pulling package <package>{$package->getId()}</package> repository.",
                'Package pull aborted.',
            ]);

            $this->registerPackageError($package, $output, 'pulling package repository');
        }
    }
}
