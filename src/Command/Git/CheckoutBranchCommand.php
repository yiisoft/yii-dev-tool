<?php

namespace Yiisoft\YiiDevTool\Command\Git;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Yiisoft\YiiDevTool\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\Component\Package\Package;

class CheckoutBranchCommand extends PackageCommand
{
    /** @var string */
    private $branch;

    protected function configure()
    {
        $this
            ->setName('git/checkout-branch')
            ->addArgument('branch', InputArgument::REQUIRED, 'Branch name')
            ->setDescription('Creates, if not exists, and checkout a git branch');

        $this->addPackageArgument();
    }

    protected function beforeProcessingPackages(InputInterface $input): void
    {
        $this->branch = $input->getArgument('branch');
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<success>✔ Done</success>';
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, "Checkout branch <em>{$this->branch}</em> in {package} repository");

        $gitWorkingCopy = $package->getGitWorkingCopy();
        $branches = $gitWorkingCopy->getBranches()->all();
        $branchExists = in_array($this->branch, $branches, true);

        if ($branchExists) {
            $gitWorkingCopy->checkout($this->branch);
        } else {
            $gitWorkingCopy->checkoutNewBranch($this->branch);
        }

        $io->done();
    }
}
