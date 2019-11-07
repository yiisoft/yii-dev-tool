<?php

namespace Yiisoft\YiiDevTool\Command;

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
            ->setName('pull')
            ->setDescription('Pull changes from package repositories');

        $this->addPackageArgument();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        foreach ($this->getTargetPackages() as $package) {
            $this->gitPull($package);
        }

        $this->showPackageErrors();
    }

    private function gitPull(Package $package): void
    {
        $io = $this->getIO();
        $header = "Pulling package <package>{$package->getId()}</package>";

        $io->header($header);

        if ($package->isConfiguredRepositoryPersonal()) {
            $gitCommand = ['git', 'pull', 'upstream', 'master'];
        } else {
            $gitCommand = ['git', 'pull'];
        }

        $process = new Process($gitCommand, $package->getPath());
        $process->setTimeout(null)->run();

        if ($process->isSuccessful()) {
            $io->write($process->getOutput() . $process->getErrorOutput());
            $io->done();
        } else {
            $output = $process->getErrorOutput();

            $io->writeln($output);
            $io->error([
                "An error occurred during pulling package <package>{$package->getId()}</package> repository.",
                'Package pull aborted.',
            ]);

            $package->setError($output, 'pulling package repository');
        }
    }
}
