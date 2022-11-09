<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\VarDumper\VarDumper;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;

final class EnableCommand extends PackageCommand
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
        $this->initPackageList();
        $io = $this->getIO();
        $packageList = $this->getPackageList();

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

        $dump = VarDumper::create($tree)->export();

        $handle = fopen(dirname(__DIR__, 3) . '/packages.local.php', 'w+');
        fwrite($handle, '<?php' . "\n\n");
        fwrite($handle, 'return ' . $dump . ';');
        fclose($handle);

        if (empty($alreadyEnabledPackages) && empty($enabledPackages)) {
            $io->info('Packages not found.');
            return Command::SUCCESS;
        }

        if (!empty($alreadyEnabledPackages)) {
            $io->write("Already enabled packages:\n — " . implode("\n — ", $alreadyEnabledPackages) . "\n");
        }
        if (!empty($enabledPackages)) {
            $io->success("Enabled packages:\n — " . implode("\n — ", $enabledPackages));
        }

        return Command::SUCCESS;
    }
}
