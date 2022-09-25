<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Component\Console;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\App\Component\Package\PackageErrorList;
use Yiisoft\YiiDevTool\App\Component\Package\PackageList;
use Yiisoft\YiiDevTool\App\YiiDevToolApplication;

/**
 * @method YiiDevToolApplication getApplication()
 */
class PackageCommand extends Command
{
    private ?OutputManager $io;
    private ?PackageList $packageList;
    private ?PackageErrorList $errorList;
    private ?bool $targetPackagesSpecifiedExplicitly;

    /** @var Package[]|null */
    private ?array $targetPackages;

    /**
     * Override this method in a subclass if you want to do something before processing the packages.
     * For example, check the input arguments.
     *
     * @param InputInterface $input
     */
    protected function beforeProcessingPackages(InputInterface $input): void
    {
    }

    /**
     * This method in a subclass should implement the processing logic of each package.
     *
     * @param Package $package
     * @noinspection PhpUnusedParameterInspection
     */
    protected function processPackage(Package $package): void
    {
        throw new RuntimeException('Package processing logic is not implemented.');
    }

    /**
     * Override this method in a subclass if you want to do something after processing the packages.
     * For example, link the packages with each other.
     */
    protected function afterProcessingPackages(): void
    {
    }

    /**
     * Override this method in a subclass if you want to output something to the console
     * in cases where a command did not output anything during its execution.
     *
     * @return string|null The message to be displayed. If null, then nothing will be output.
     */
    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return null;
    }

    /**
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

    protected function getErrorsList(): PackageErrorList
    {
        return $this->errorList;
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

            $this->errorList = new PackageErrorList();
        } catch (InvalidArgumentException $e) {
            $io->error([
                'Invalid local package configuration <file>packages.local.php</file>',
                $e->getMessage(),
                'See <file>packages.local.php.example</file> for configuration examples.',
            ]);

            exit(1);
        }
    }

    private function initTargetPackages(InputInterface $input): void
    {
        if ($this->packageList === null) {
            throw new RuntimeException('Package list is not initialized.');
        }

        $io = $this->getIO();
        $commaSeparatedPackageIds = $input->getArgument('packages');

        if ($commaSeparatedPackageIds === null) {
            $this->targetPackagesSpecifiedExplicitly = false;
            $this->targetPackages = $this->packageList->getEnabledPackages();

            return;
        }

        $targetPackageIds = array_unique(explode(',', $commaSeparatedPackageIds));
        $problemsFound = false;
        $targetPackages = [];
        foreach ($targetPackageIds as $targetPackageId) {
            $package = $this->packageList->getPackage($targetPackageId);

            if ($package === null) {
                $io->error("Package <package>$targetPackageId</package> not found in <file>packages.php</file>");
                $problemsFound = true;
                continue;
            }

            if ($package->disabled()) {
                $io->error("Package <package>$targetPackageId</package> disabled in <file>packages.local.php</file>");
                $problemsFound = true;
                continue;
            }

            $targetPackages[] = $package;
        }

        if ($problemsFound) {
            exit(1);
        }

        $this->targetPackagesSpecifiedExplicitly = true;
        $this->targetPackages = $targetPackages;
    }

    private function isCurrentInstallationValid(Package $package): bool
    {
        $io = $this->getIO();

        if (!$package->isGitRepositoryCloned()) {
            // TODO: Implement extensible validation instead of checking command names
            if (in_array($this->getName(), ['install', 'update', 'git/clone'], true)) {
                return true;
            }

            $io->error([
                "Package <package>{$package->getId()}</package> repository is not cloned.",
                'To fix, run the command:',
                '',
                "  <cmd>{$this->getExampleCommandPrefix()}yii-dev install {$package->getId()}</cmd>",
            ]);

            if (!$this->areTargetPackagesSpecifiedExplicitly()) {
                $io->error([
                    'You can also disable the package in <file>packages.local.php</file>',
                    'See <file>packages.local.php.example</file> for configuration examples.',
                ]);
            }

            return false;
        }

        $gitWorkingCopy = $package->getGitWorkingCopy();
        $remoteOriginUrl = $gitWorkingCopy->getRemoteUrl('origin');
        if ($package->getConfiguredRepositoryUrl() !== $remoteOriginUrl) {
            $io->error([
                "Package <package>{$package->getId()}</package> repository is cloned from <file>{$remoteOriginUrl}</file>, but url <file>{$package->getConfiguredRepositoryUrl()}</file> is configured.",
                'To fix, delete the existing working copy of the repository and run the command:',
                '',
                "  <cmd>{$this->getExampleCommandPrefix()}yii-dev install {$package->getId()}</cmd>",
                '',
                'Before deleting, make sure that you do not have local changes, branches and tags that are not sent to remote repository.',
                'You can also reconfigure the package repository url in <file>packages.local.php</file>',
                'See <file>packages.local.php.example</file> for configuration examples.',
            ]);

            return false;
        }

        return true;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initPackageList();
        $this->initTargetPackages($input);

        $io = $this->getIO();

        $this->beforeProcessingPackages($input);
        $packages = $this->getTargetPackages();
        sort($packages);
        foreach ($packages as $package) {
            if ($this->isCurrentInstallationValid($package)) {
                $this->processPackage($package);
            }
        }

        $io->clearPreparedPackageHeader();
        $this->afterProcessingPackages();

        $this->showPackageErrors();

        if ($io->nothingHasBeenOutput()) {
            $message = $this->getMessageWhenNothingHasBeenOutput();
            if ($message !== null) {
                $io
                    ->important()
                    ->info($message);
            }
        }

        return Command::SUCCESS;
    }

    protected function getPackageList(): PackageList
    {
        return $this->packageList;
    }

    protected function areTargetPackagesSpecifiedExplicitly(): bool
    {
        if ($this->targetPackagesSpecifiedExplicitly === null) {
            throw new RuntimeException('Target packages are not initialized.');
        }

        return $this->targetPackagesSpecifiedExplicitly;
    }

    /**
     * @return Package[]
     */
    protected function getTargetPackages(): array
    {
        if ($this->targetPackages === null) {
            throw new RuntimeException('Target packages are not initialized.');
        }

        return $this->targetPackages;
    }

    protected function registerPackageError(Package $package, string $message, string $during): void
    {
        $this->errorList->set($package, $message, $during);
    }

    protected function doesPackageContainErrors(Package $package): bool
    {
        return $this->errorList->has($package);
    }

    private function showPackageErrors(): void
    {
        $io = $this->getIO();

        if (count($this->errorList) > 0) {
            $io
                ->important()
                ->info([
                    '<em>',
                    '=======================================================================',
                    'SUMMARY OF ERRORS THAT OCCURRED',
                    '=======================================================================',
                    '</em>',
                ]);

            foreach ($this->errorList as $packageError) {
                $io->preparePackageHeader(
                    $packageError->getPackage(),
                    "Package {package} error occurred during <em>{$packageError->getDuring()}</em>:"
                );

                $io
                    ->important()
                    ->info($packageError->getMessage());
            }
        }
    }

    /**
     * @return string Console command prefix that works in current environment.
     */
    protected function getExampleCommandPrefix(): string
    {
        $shell = getenv('SHELL');
        $isBash = ($shell && stripos($shell, 'bash')) !== false;
        return $isBash ? './' : '';
    }
}
