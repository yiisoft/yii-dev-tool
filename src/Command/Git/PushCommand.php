<?php

namespace Yiisoft\YiiDevTool\Command\Git;

use GitWrapper\GitException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\YiiDevTool\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\Component\Package\Package;

class PushCommand extends PackageCommand
{
    protected function configure()
    {
        $this
            ->setName('git/push')
            ->setDescription('Push changes into package repositories');

        $this->addPackageArgument();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        foreach ($this->getTargetPackages() as $package) {
            $this->gitPush($package);
        }

        $io = $this->getIO();
        $io->clearPreparedPackageHeader();

        $this->showPackageErrors();

        if ($io->nothingHasBeenOutput()) {
            $io->important()->done();
        }
    }

    private function gitPush(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, "Pushing package {package}");

        $gitWorkingCopy = $package->getGitWorkingCopy();

        try {
            $currentBranch = $gitWorkingCopy->getBranches()->head();

            if ($currentBranch === 'master') {
                $gitWorkingCopy->push();
            } else {
                $gitWorkingCopy->push('origin', $currentBranch);
            }

            $io->done();
        } catch (GitException $e) {
            $io->error([
                "An error occurred during pushing package <package>{$package->getId()}</package> repository.",
                $e->getMessage(),
                'Package push aborted.',
            ]);

            $package->setError($e->getMessage(), 'pushing package repository');
        }
    }
}
