<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\VarDumper\VarDumper;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;

#[AsCommand(
    name: 'switch',
    description: 'Enable specified packages and disable others'
)]
final class SwitchCommand extends PackageCommand
{
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
        $this->initPackageList();
        $io = $this->getIO();
        $packageList = $this->getPackageList();

        $enablePackageIds = array_unique(explode(',', (string) $input->getArgument('packages')));
        $enablePackageIds = array_filter($enablePackageIds, static fn ($id) => !empty($id));
        if (empty($enablePackageIds)) {
            $io->error('Please, specify packages separated by commas.');
            return Command::FAILURE;
        }
        foreach ($enablePackageIds as $packageId) {
            if (!$packageList->hasPackage($packageId)) {
                $io->error('Package "' . $packageId . '" not found.');
                return Command::FAILURE;
            }
        }

        $enabledPackages = [];
        $disabledPackages = [];

        foreach ($packageList->getAllPackages() as $packageId => $package) {
            if (in_array($packageId, $enablePackageIds, true)) {
                $package->setEnabled(true);
                $enabledPackages[] = $packageId;
            } elseif ($package->enabled()) {
                $package->setEnabled(false);
                $disabledPackages[] = $packageId;
            }
        }

        $tree = $packageList->getTree();

        $dump = VarDumper::create($tree)->export();

        $handle = fopen(dirname(__DIR__, 3) . '/packages.local.php', 'w+');
        fwrite($handle, '<?php' . "\n\n");
        fwrite($handle, 'return ' . $dump . ';');
        fclose($handle);

        $io->success("\n + " . implode("\n + ", $enabledPackages));
        if (!empty($disabledPackages)) {
            $io->error("\n — " . implode("\n — ", $disabledPackages));
        }

        return Command::SUCCESS;
    }
}
