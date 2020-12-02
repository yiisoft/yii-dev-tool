<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Command\Git;

use Symfony\Component\Process\Process;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\Component\Console\PackageCommand;

class PullCommand extends PackageCommand
{
    protected function configure()
    {
        $this
            ->setName('git/pull')
            ->setDescription('Pull changes from package repositories');

        $this->addPackageArgument();
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<em>Nothing to pull</em>';
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, "Pulling package {package}");

        if ($package->isConfiguredRepositoryPersonal()) {
            $gitCommand = ['git', 'pull', 'upstream', 'master'];
        } else {
            $gitCommand = ['git', 'pull'];
        }

        $process = new Process($gitCommand, $package->getPath());
        $process->setTimeout(null)->run();

        if ($process->isSuccessful()) {
            $output = $process->getOutput() . $process->getErrorOutput();

            $io->important(trim($output) !== 'Already up to date.')->info($output);
            $io->done();
        } else {
            $output = $process->getErrorOutput();

            $io->important()->info($output);
            $io->error([
                "An error occurred during pulling package <package>{$package->getId()}</package> repository.",
                'Package pull aborted.',
            ]);

            $this->registerPackageError($package, $output, 'pulling package repository');
        }
    }
}
