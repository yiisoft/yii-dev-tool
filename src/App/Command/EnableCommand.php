<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\VarDumper\VarDumper;
use Yiisoft\YiiDevTool\App\Component\Console\YiiDevToolStyle;
use Yiisoft\YiiDevTool\App\YiiDevToolApplication;

/** @method YiiDevToolApplication getApplication()**/
final class EnableCommand extends Command
{
    protected static $defaultName = 'enable';
    protected static $defaultDescription = 'Enable packages';

    protected function configure()
    {
        $this->addArgument(
            'packages',
            InputArgument::OPTIONAL,
            <<<DESCRIPTION
            Package names separated by commas. For example: <fg=cyan;options=bold>rbac,di,demo,db-mysql</>
            Array keys from <fg=blue;options=bold>package.php</> configuration can be specified.
            If packages are not specified, then command will be applied to <fg=yellow>all packages.</>
            DESCRIPTION
        );
        $this->addOption('all', 'a', InputOption::VALUE_NONE, 'Enable all packages');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new YiiDevToolStyle($input, $output);

        $configs = require $this->getApplication()->getConfigFile();
        if (empty($configs['packages'])) {
            $io->error('There is no list of packages in the configs, or it is empty.');
            return Command::FAILURE;
        }
        $packages = $configs['packages'];

        $enableAll = $input->getOption('all');
        if ($enableAll) {
            $enablePackageIds = array_keys($packages);
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
            if (!isset($packages[$packageId])) {
                continue;
            }

            if ($packages[$packageId]) {
                $alreadyEnabledPackages[] = $packageId;
            } else {
                $packages[$packageId] = true;
                $enabledPackages[] = $packageId;
            }
        }

        $configs['packages'] = $packages;
        $exportArray = VarDumper::create($configs)->export();
        file_put_contents($this->getApplication()->getConfigFile(), "<?php\n\nreturn $exportArray;\n");

        if (empty($alreadyEnabledPackages) && empty($enabledPackages)) {
            $io->info('Packages not found.');
            return Command::SUCCESS;
        }

        if (!empty($alreadyEnabledPackages)) {
            $io->text("Already enabled packages:\n — " . implode("\n — ", $alreadyEnabledPackages) . "\n");
        }
        if (!empty($enabledPackages)) {
            $io->success("Enabled packages:\n — " . implode("\n — ", $enabledPackages));
        }

        return Command::SUCCESS;
    }
}
