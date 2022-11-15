<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Packages;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\VarDumper\VarDumper;
use Yiisoft\YiiDevTool\App\Component\Console\YiiDevToolStyle;
use Yiisoft\YiiDevTool\App\YiiDevToolApplication;

/** @method YiiDevToolApplication getApplication() */
final class SwitchCommand extends Command
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
        $io = new YiiDevToolStyle($input, $output);

        if (!file_exists($this->getApplication()->getConfigFile())) {
            $io->error('The config file does not exist. Initialize the dev tool.');
            exit(1);
        }
        $configs = require $this->getApplication()->getConfigFile();
        if (empty($configs['packages'])) {
            $io->error('There is no list of packages in the configs, or it is empty.');
            return Command::FAILURE;
        }
        $packages = $configs['packages'];

        $commaSeparatedPackageIds = $input->getArgument('packages');
        $enablePackageIds = array_unique(explode(',', $commaSeparatedPackageIds));
        $enablePackageIds = array_filter($enablePackageIds, static fn ($id) => !empty($id));
        if (empty($enablePackageIds)) {
            $io->error('Please, specify packages separated by commas.');
            return Command::FAILURE;
        }

        $notFoundPackages = [];
        foreach ($enablePackageIds as $packageId) {
            if (!isset($packages[$packageId])) {
                $notFoundPackages[] = $packageId;
            }
        }

        if (!empty($notFoundPackages)) {
            $io->error("Not found Packages: \n " . implode("\n ", $notFoundPackages));
            return Command::FAILURE;
        }

        $disabledPackages = [];
        $enabledPackages = [];
        foreach ($packages as $packageId => $packageValue) {
            if (in_array($packageId, $enablePackageIds, true)) {
                $packages[$packageId] = true;
                $enabledPackages[] = $packageId;
            } else {
                $packages[$packageId] = false;
                $disabledPackages[] = $packageId;
            }
        }

        $configs['packages'] = $packages;
        $exportArray = VarDumper::create($configs)->export();
        file_put_contents($this->getApplication()->getConfigFile(), "<?php\n\nreturn $exportArray;\n");

        if (!empty($enabledPackages)) {
            $io->success("Enable packages: \n + " . implode("\n + ", $enabledPackages));
        }

        if (!empty($disabledPackages)) {
            $io->error("Disabled Packages: \n - " . implode("\n — ", $disabledPackages));
        }

        return Command::SUCCESS;
    }
}
