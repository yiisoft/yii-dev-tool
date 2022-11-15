<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Packages;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\YiiDevTool\App\Component\Console\YiiDevToolStyle;
use Yiisoft\YiiDevTool\App\YiiDevToolApplication;

/** @method YiiDevToolApplication getApplication() */
final class RemoveCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('packages/remove')
            ->setAliases(['remove'])
            ->setDescription('Remove packages')
            ->addArgument(
                'packages',
                InputArgument::OPTIONAL,
                <<<DESCRIPTION
                Package names separated by commas. For example: <fg=cyan;options=bold>rbac,di,demo,db-mysql</>
                DESCRIPTION
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new YiiDevToolStyle($input, $output);

        $config = $this->getApplication()->getConfig();
        $packages = $config->getPackages();

        $commaSeparatedPackageIds = $input->getArgument('packages');
        if ($commaSeparatedPackageIds === null) {
            $io->error('Please, specify packages separated by commas.');
            return Command::FAILURE;
        }
        $removePackagesIds = array_unique(explode(',', $commaSeparatedPackageIds));

        $removePackages = [];
        foreach ($removePackagesIds as $package) {
            if (isset($packages[$package])) {
                $removePackages[] = $package;
                unset($packages[$package]);
            } else {
                $io->error("Package `$package` is not in the list of packages in the config.");
            }
        }
        ksort($packages);

        $config->change('packages', $packages);

        if (!empty($removePackages)) {
            $io->success('Packages removed: ' . implode(', ', $removePackages));
        }

        return Command::SUCCESS;
    }
}
