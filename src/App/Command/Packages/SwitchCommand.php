<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Packages;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\YiiDevToolApplication;

/** @method YiiDevToolApplication getApplication() */
final class SwitchCommand extends PackageCommand
{
    protected function configure(): void
    {
        $this
            ->setName('packages/switch')
            ->setAliases(['switch'])
            ->setDescription('Enable specified packages and disable others')
            ->addArgument(
                'packages',
                InputArgument::REQUIRED,
                <<<DESCRIPTION
                Package names separated by commas. For example: <fg=cyan;options=bold>rbac,di,demo,db-mysql</>
                Array keys from <fg=blue;options=bold>package.php</> configuration can be specified.</>
                DESCRIPTION
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initPackageList();

        $io = $this->getIO();
        $config = $this->getConfig();
        $packagesList = $this->getPackageList();

        $commaSeparatedPackageIds = $input->getArgument('packages');
        $enablePackageIds = array_unique(explode(',', $commaSeparatedPackageIds));
        $enablePackageIds = array_filter($enablePackageIds, static fn ($id) => !empty($id));
        if (empty($enablePackageIds)) {
            $io->error('Please, specify packages separated by commas.');
            return Command::FAILURE;
        }

        $notFoundPackages = [];
        foreach ($enablePackageIds as $packageId) {
            $package = $packagesList->getPackage($packageId);
            if ($package === null) {
                $notFoundPackages[] = $packageId;
            }
        }

        if (!empty($notFoundPackages)) {
            $io->error("Not found Packages: \n " . implode("\n ", $notFoundPackages));
            return Command::FAILURE;
        }

        $disabledPackages = [];
        $enabledPackages = [];
        foreach ($packagesList->getAllPackages() as $packageId => $package) {
            if (in_array($packageId, $enablePackageIds, true)) {
                $package->setEnabled(true);
                $enabledPackages[] = $packageId;
            } else {
                $package->setEnabled(false);
                $disabledPackages[] = $packageId;
            }
        }

        $tree = $packagesList->getTree();
        $config->change('packages', $tree);

        if (!empty($enabledPackages)) {
            $io->important()->success("Enable packages: \n + " . implode("\n + ", $enabledPackages));
        }

        if (!empty($disabledPackages)) {
            $io->error("Disabled Packages: \n - " . implode("\n — ", $disabledPackages));
        }

        return Command::SUCCESS;
    }
}
