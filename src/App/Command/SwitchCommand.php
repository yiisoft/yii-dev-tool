<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\VarDumper\VarDumper;
use Yiisoft\YiiDevTool\App\Component\Console\OutputManager;
use Yiisoft\YiiDevTool\App\Component\Console\YiiDevToolStyle;
use Yiisoft\YiiDevTool\App\Component\Package\PackageList;

final class SwitchCommand extends Command
{
    protected static $defaultName = 'switch';
    protected static $defaultDescription = 'Enable specified packages and disable others';
    private ?PackageList $packageList = null;
    private $io;

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

    /**
     * TODO: move to common class
     * Use this method to get a root directory of the tool.
     *
     * Commands and components can be moved as a result of refactoring,
     * so you should not rely on their location in the file system.
     *
     * @return string Path to the root directory of the tool WITH a TRAILING SLASH.
     */
    protected function getAppRootDir(): string
    {
        return rtrim($this
                ->getApplication()
                ->getRootDir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new OutputManager(new YiiDevToolStyle($input, $output));
    }
    protected function getIO(): OutputManager
    {
        if ($this->io === null) {
            throw new RuntimeException('IO is not initialized.');
        }

        return $this->io;
    }
    private function initPackageList(): void
    {
        $io = $this->getIO();

        try {
            $ownerPackages = require $this->getAppRootDir() . 'owner-packages.php';
            if (!preg_match('/^[a-z0-9][a-z0-9-]*[a-z0-9]$/i', $ownerPackages)) {
                $io->error([
                    'The packages owner can only contain the characters [a-z0-9-], and the character \'-\' cannot appear at the beginning or at the end.',
                    'See <file>owner-packages.php</file> to set the packages owner.',
                ]);

                exit(1);
            }

            $this->packageList = new PackageList(
                $ownerPackages,
                $this->getAppRootDir() . 'packages.php',
                $this->getAppRootDir() . 'dev',
            );
        } catch (InvalidArgumentException $e) {
            $io->error([
                'Invalid local package configuration <file>packages.local.php</file>',
                $e->getMessage(),
                'See <file>packages.local.php.example</file> for configuration examples.',
            ]);

            exit(1);
        }
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initPackageList();
        $io = $this->io;

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
            $package = $this->packageList->getPackage($packageId);

            $package->setEnabled(true);
            $enabledPackages[] = $packageId;
        }

        $disabledPackages = [];
        foreach ($packages as $packageId => $enabled) {
            $package = $this->packageList->getPackage($packageId);

            if ($enabled && !in_array($packageId, $enablePackageIds, true)) {
                $package->setEnabled(false);
                $disabledPackages[] = $packageId;
            }
        }

        $tree = $this->packageList->getTree();

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
