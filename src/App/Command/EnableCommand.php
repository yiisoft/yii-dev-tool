<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\YiiDevTool\App\Component\Console\YiiDevToolStyle;

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

        $packages = require dirname(__DIR__, 3) . '/packages.php';

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

        $enabledPackages = [];
        foreach ($enablePackageIds as $packageId) {
            if (isset($packages[$packageId])) {
                $packages[$packageId] = true;
                $enabledPackages[] = $packageId;
            }
        }

        $handle = fopen(dirname(__DIR__, 3) . '/packages.local.php', 'w+');
        fwrite($handle, '<?php' . "\n\n");
        fwrite($handle, 'return [' . "\n");
        foreach ($packages as $packageId => $enabled) {
            fwrite($handle, '    \'' . $packageId . '\' => ' . ($enabled ? 'true' : 'false') . ',' . "\n");
        }
        fwrite($handle, '];' . "\n");
        fclose($handle);

        if (!empty($enabledPackages)) {
            $io->success("Enabled packages:\n — " . implode("\n — ", $enabledPackages));
        } else {
            $io->info('Packages not found.');
        }

        return Command::SUCCESS;
    }
}
