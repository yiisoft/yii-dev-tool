<?php

namespace Yiisoft\YiiDevTool\Command\Git;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Yiisoft\YiiDevTool\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\Component\Package\Package;

class PullCommand extends PackageCommand
{
    protected function configure()
    {
        $this
            ->setName('git/pull')
            ->setDescription('Pull changes from package repositories');

        $this->addPackageArgument();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        foreach ($this->getTargetPackages() as $package) {
            $this->gitPull($package);
        }

        $io = $this->getIO();
        $io->clearPreparedPackageHeader();

        $this->showPackageErrors();

        if ($io->nothingHasBeenOutput()) {
            $io->important()->warning('Nothing to pull');
        }
    }

    private function gitPull(Package $package): void
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

            $package->setError($output, 'pulling package repository');
        }
    }
}
