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
final class EnableCommand extends PackageCommand
{
    protected function configure()
    {
        $this
            ->setName('packages/enable')
            ->setAliases(['enable'])
            ->setDescription('Enable packages')
            ->addArgument(
                'packages',
                InputArgument::OPTIONAL,
                <<<DESCRIPTION
                Package names separated by commas. For example: <fg=cyan;options=bold>rbac,di,demo,db-mysql</>
                Array keys from configuration can be specified.</>
                DESCRIPTION
            )
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Enable all packages');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initPackageList();
        $io = $this->getIO();
        $packageList = $this->getPackageList();
        $config = $this->getConfig();

        $enableAll = $input->getOption('all');
        if ($enableAll) {
            $enablePackageIds = array_keys($packageList->getAllPackages());
        } else {
            $commaSeparatedPackageIds = $input->getArgument('packages');
            if ($commaSeparatedPackageIds === null) {
                $io->error('Please, specify packages separated by commas or use flag "--all".');
                return Command::FAILURE;
            }
            $enablePackageIds = array_unique(explode(',', $commaSeparatedPackageIds));
        }

        $alreadyEnabledPackages = [];
        $enabledPackages = [];
        foreach ($enablePackageIds as $packageId) {
            $package = $packageList->getPackage($packageId);

            if ($package === null) {
                continue;
            }

            if ($package->enabled()) {
                $alreadyEnabledPackages[] = $packageId;
            } else {
                $package->setEnabled(true);
                $enabledPackages[] = $packageId;
            }
        }

        $tree = $packageList->getTree();
        $config->change('packages', $tree);

        if (empty($alreadyEnabledPackages) && empty($enabledPackages)) {
            $io->info('Packages not found.');
            return Command::SUCCESS;
        }

        if (!empty($alreadyEnabledPackages)) {
            $io->important()->info("Already enabled packages:\n — " . implode("\n — ", $alreadyEnabledPackages) . "\n");
        }
        if (!empty($enabledPackages)) {
            $io->important()->success("Enabled packages:\n — " . implode("\n — ", $enabledPackages));
        }

        return Command::SUCCESS;
    }
}
