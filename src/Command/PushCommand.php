<?php

namespace Yiisoft\YiiDevTool\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Yiisoft\YiiDevTool\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\Component\Package\Package;

class PushCommand extends PackageCommand
{
    protected function configure()
    {
        $this
            ->setName('push')
            ->setDescription('Push changes into package repositories');

        $this->addPackageArgument();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        foreach ($this->getTargetPackages() as $package) {
            $this->gitPush($package);
        }

        $this->showPackageErrors();
    }

    private function gitPush(Package $package): void
    {
        $io = $this->getIO();
        $header = "Pushing package <package>{$package->getId()}</package>";

        $io->header($header);

        $process = new Process(['git', 'push'], $package->getPath());
        $process->setTimeout(null)->run();

        if ($process->isSuccessful()) {
            $io->write($process->getOutput() . $process->getErrorOutput());
            $io->done();
        } else {
            $output = $process->getErrorOutput();

            $io->writeln($output);
            $io->error([
                "An error occurred during pushing package <package>{$package->getId()}</package> repository.",
                'Package push aborted.',
            ]);

            $package->setError($output, 'pushing package repository');
        }
    }
}
