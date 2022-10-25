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

/** @method YiiDevToolApplication getApplication()  **/
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

        $configs = require $this->getApplication()->getConfigFile();
        if (empty($configs['packages'])) {
            $io->error('There is no list of packages in the configs, or it is empty.');
            return Command::FAILURE;
        }
        $packages = $configs['packages'];

        $commaSeparatedPackageIds = $input->getArgument('packages');
        if ($commaSeparatedPackageIds === null) {
            $io->error('Please, specify packages separated by commas or use flag "--all".');
            return Command::FAILURE;
        }
        $removePackages = array_unique(explode(',', $commaSeparatedPackageIds));

        foreach ($removePackages as $package) {
            if (isset($packages[$package])){
                unset($packages[$package]);
            }
        }
        ksort($packages);

        $configs['packages'] = $packages;
        $exportArray = VarDumper::create($configs)->export();
        file_put_contents($this->getApplication()->getConfigFile(), "<?php\n\nreturn $exportArray;\n");

        $io->success('Packages removed: ' . implode(', ', $removePackages));
        return Command::SUCCESS;
    }
}
