<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\YiiDevTool\App\Component\Console\YiiDevToolStyle;

final class SwitchCommand extends Command
{
    protected static $defaultName = 'switch';
    protected static $defaultDescription = 'Enable specified packages and disable others';

    protected function configure(): void
    {
        $this->addArgument(
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

        $packages = require dirname(__DIR__, 3) . '/packages.php';

        $enablePackageIds = array_unique(explode(',', (string)$input->getArgument('packages')));
        $enablePackageIds = array_filter($enablePackageIds, static fn ($id) => !empty($id));
        if (empty($enablePackageIds)) {
            $io->error('Please, specify packages separated by commas.');
            return Command::FAILURE;
        }
        foreach ($enablePackageIds as $packageId) {
            if (!array_key_exists($packageId, $packages)) {
                $io->error('Package "' . $packageId . '" not found.');
                return Command::FAILURE;
            }
        }

        $enabledPackages = [];
        foreach ($enablePackageIds as $packageId) {
            $packages[$packageId] = true;
            $enabledPackages[] = $packageId;
        }

        $disabledPackages = [];
        foreach ($packages as $packageId => $enabled) {
            if ($enabled && !in_array($packageId, $enablePackageIds, true)) {
                $packages[$packageId] = false;
                $disabledPackages[] = $packageId;
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

        $io->success("\n + " . implode("\n + ", $enabledPackages));
        if (!empty($disabledPackages)) {
            $io->error("\n — " . implode("\n — ", $disabledPackages));
        }

        return Command::SUCCESS;
    }
}
