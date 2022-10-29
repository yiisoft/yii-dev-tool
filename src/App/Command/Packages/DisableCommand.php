<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Packages;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\VarDumper\VarDumper;
use Yiisoft\YiiDevTool\App\Component\Console\YiiDevToolStyle;
use Yiisoft\YiiDevTool\App\YiiDevToolApplication;

/** @method YiiDevToolApplication getApplication()  **/
final class DisableCommand extends Command
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
        $io = new YiiDevToolStyle($input, $output);

        $configs = require $this->getApplication()->getConfigFile();
        if (empty($configs['packages'])) {
            $io->error('List of packages in configs is empty.');
            return Command::FAILURE;
        }
        $packages = $configs['packages'];

        $disableAll = $input->getOption('all');
        if ($disableAll) {
            $disablePackageIds = array_keys($packages);
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
            if (!isset($packages[$packageId])) {
                continue;
            }

            if ($packages[$packageId]) {
                $packages[$packageId] = false;
                $disabledPackages[] = $packageId;
            } else {
                $alreadyDisabledPackages[] = $packageId;
            }
        }

        $configs['packages'] = $packages;
        $exportArray = VarDumper::create($configs)->export();
        file_put_contents($this->getApplication()->getConfigFile(), "<?php\n\nreturn $exportArray;\n");

        if (empty($alreadyDisabledPackages) && empty($disabledPackages)) {
            $io->info('Packages not found.');
            return Command::SUCCESS;
        }

        if (!empty($alreadyDisabledPackages)) {
            $io->text("Already disabled packages:\n — " . implode("\n — ", $alreadyDisabledPackages) . "\n");
        }
        if (!empty($disabledPackages)) {
            $io->success("Disabled packages:\n — " . implode("\n — ", $disabledPackages));
        }

        return Command::SUCCESS;
    }
}
