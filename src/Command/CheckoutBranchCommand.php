<?php

namespace Yiisoft\YiiDevTool\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\YiiDevTool\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\Component\Package\Package;

class CheckoutBranchCommand extends PackageCommand
{
    /** @var string */
    private $branch;

    protected function configure()
    {
        $this
            ->setName('checkout-branch')
            ->addArgument('branch', InputArgument::REQUIRED, 'Branch name')
            ->setDescription('Creates, if not exists, and checkout a git branch');

        $this->addPackageArgument();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->branch = $input->getArgument('branch');

        foreach ($this->getTargetPackages() as $package) {
            $this->gitCheckoutBranch($package);
        }

        $this->showPackageErrors();
    }

    private function gitCheckoutBranch(Package $package): void
    {
        $io = $this->getIO();
        $header = "Checkout branch <em>{$this->branch}</em> in <package>{$package->getId()}</package> repository";

        $io->header($header);

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
