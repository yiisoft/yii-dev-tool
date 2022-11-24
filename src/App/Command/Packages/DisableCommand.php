<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Packages;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\YiiDevToolApplication;

/** @method YiiDevToolApplication getApplication() */
final class DisableCommand extends PackageCommand
{
    protected function configure()
    {
        $this
            ->setName('packages/disable')
            ->setAliases(['disable'])
            ->setDescription('Disable packages')
            ->addArgument(
                'packages',
                InputArgument::OPTIONAL,
                <<<DESCRIPTION
                Package names separated by commas. For example: <fg=cyan;options=bold>rbac,di,demo,db-mysql</>
                Array keys from configuration can be specified.</>
                DESCRIPTION
            )
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Disable all packages');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initPackageList();
        $io = $this->getIO();

        $config = $this->getConfig();
        $packageList = $this->getPackageList();

        $disableAll = $input->getOption('all');
        if ($disableAll) {
            $disablePackageIds = array_keys($packageList->getAllPackages());
        } else {
            $commaSeparatedPackageIds = $input->getArgument('packages');
            if ($commaSeparatedPackageIds === null) {
                $io->error('Please, specify packages separated by commas or use flag "--all".');
                return Command::FAILURE;
            }
            $disablePackageIds = array_unique(explode(',', $commaSeparatedPackageIds));
        }

        $alreadyDisabledPackages = [];
        $disabledPackages = [];
        foreach ($disablePackageIds as $packageId) {
            $package = $packageList->getPackage($packageId);

            if ($package === null) {
                continue;
            }
            if ($package->enabled()) {
                $package->setEnabled(false);
                $disabledPackages[] = $packageId;
            } else {
                $alreadyDisabledPackages[] = $packageId;
            }
        }

        $packages = $packageList->getTree();
        $config->change('packages', $packages);

        if (empty($alreadyDisabledPackages) && empty($disabledPackages)) {
            $io->important()->info('Packages not found.');
            return Command::SUCCESS;
        }

        if (!empty($alreadyDisabledPackages)) {
            $io->important()->info("Already disabled packages:\n — " . implode("\n — ", $alreadyDisabledPackages) . "\n");
        }
        if (!empty($disabledPackages)) {
            $io->important()->success("Disabled packages:\n — " . implode("\n — ", $disabledPackages));
        }

        return Command::SUCCESS;
    }
}
