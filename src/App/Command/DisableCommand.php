<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\VarDumper\VarDumper;
use Yiisoft\YiiDevTool\App\Component\Console\OutputManager;
use Yiisoft\YiiDevTool\App\Component\Console\YiiDevToolStyle;
use Yiisoft\YiiDevTool\App\Component\Package\PackageList;

final class DisableCommand extends Command
{
    protected static $defaultName = 'disable';
    protected static $defaultDescription = 'Disable packages';
    private ?PackageList $packageList = null;
    private $io;
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
        $this->addOption('all', 'a', InputOption::VALUE_NONE, 'Disable all packages');
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

        $disableAll = $input->getOption('all');
        if ($disableAll) {
            $disablePackageIds = array_keys($this->packageList->getAllPackages());
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
            $package = $this->packageList->getPackage($packageId);

            if ($package === null) {
                continue;
            }
            if ($package->enabled()) {
                $package->setEnabled(false);
                $disabledPackages[] = $packageId;
            } else {
                $alreadyDisabledPackages[] = $packageId;
            }
        }

        $tree = $this->packageList->getTree();

        $dump = VarDumper::create($tree)->export();

        $handle = fopen(dirname(__DIR__, 3) . '/packages.local.php', 'w+');
        fwrite($handle, '<?php' . "\n\n");
        fwrite($handle, 'return ' . $dump . ';');
        fclose($handle);


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
