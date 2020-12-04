<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Git;

use GitWrapper\Exception\GitException;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;

final class PushCommand extends PackageCommand
{
    protected function configure()
    {
        $this
            ->setName('git/push')
            ->setDescription('Push changes into package repositories');

        $this->addPackageArgument();
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<success>✔ Done</success>';
    }

    protected function processPackage(Package $package): void
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

            $this->registerPackageError($package, $e->getMessage(), 'pushing package repository');
        }
    }
}
