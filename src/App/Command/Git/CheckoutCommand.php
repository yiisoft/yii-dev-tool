<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Git;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;

final class CheckoutCommand extends PackageCommand
{
    protected static $defaultName = 'git/checkout';
    protected static $defaultDescription = 'Create a branch if does not exist, checkout a branch if it exists';

    /** @var string */
    private string $branch;

    protected function configure(): void
    {
        $this
            ->setAliases(['checkout'])
            ->addArgument('branch', InputArgument::REQUIRED, 'Branch name');

        parent::configure();
    }

    protected function beforeProcessingPackages(InputInterface $input): void
    {
        $this->branch = (string)$input->getArgument('branch');
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
        $branches = $gitWorkingCopy
            ->getBranches()
            ->all();
        $branchExists = in_array($this->branch, $branches, true);

        if ($branchExists) {
            $gitWorkingCopy->checkout($this->branch);
        } else {
            $gitWorkingCopy->checkoutNewBranch($this->branch);
        }

        $io->done();
    }
}
