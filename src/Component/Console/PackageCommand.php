<?php

namespace Yiisoft\YiiDevTool\Component\Console;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\YiiDevTool\Component\Package\Package;
use Yiisoft\YiiDevTool\Component\Package\PackageList;

class PackageCommand extends Command
{
    /** @var OutputManager|null */
    private $io;

    /** @var PackageList|null */
    private $packageList;

    /** @var bool|null */
    private $targetPackagesSpecifiedExplicitly;

    /** @var Package[]|null */
    private $targetPackages;

    protected function addPackageArgument(): void
    {
        $this->addArgument(
            'packages',
            InputArgument::OPTIONAL,
            <<<DESCRIPTION
Package names separated by commas. For example: <fg=cyan;options=bold>rbac,di,yii-demo,db-mysql</>
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

    private function initPackageList(): void
    {
        $io = $this->getIO();

        try {
            $this->packageList = new PackageList(__DIR__ . '/../../../packages.php');
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
            if (in_array($this->getName(), ['install', 'update'], true)) {
                return true;
            }

            $io->error([
                "Package <package>{$package->getId()}</package> repository is not cloned.",
                'To fix, run the command:',
                '',
                "  <cmd>./yii-dev install {$package->getId()}</cmd>",
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
                "  <cmd>./yii-dev install {$package->getId()}</cmd>",
                '',
                'Before deleting, make sure that you do not have local changes, branches and tags that are not sent to remote repository.',
                'You can also reconfigure the package repository url in <file>packages.local.php</file>',
                'See <file>packages.local.php.example</file> for configuration examples.',
            ]);

            return false;
        }

        // TODO: Implement extensible validation instead of checking command names
        if ($this->getName() === 'pull') {
            if ($package->isConfiguredRepositoryPersonal()) {
                if (!$gitWorkingCopy->hasRemote('upstream')) {
                    $io->error([
                        "Package <package>{$package->getId()}</package> repository is personal and has no remote 'upstream'.",
                        'To fix, run the command:',
                        '',
                        "  <cmd>./yii-dev install {$package->getId()}</cmd>",
                    ]);

                    return false;
                }
            }
        }

        return true;
    }

    private function checkCurrentInstallation(): void
    {
        $problemsFound = false;
        foreach ($this->getTargetPackages() as $package) {
            if (!$this->isCurrentInstallationValid($package)) {
                $problemsFound = true;
            }
        }

        if ($problemsFound) {
            exit(1);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initPackageList();
        $this->initTargetPackages($input);
        $this->checkCurrentInstallation();
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

    protected function showPackageErrors(): void
    {
        $io = $this->getIO();
        $packagesWithError = $this->getPackageList()->getPackagesWithError();

        if (count($packagesWithError)) {
            $io->important()->info([
                '<em>',
                '=======================================================================',
                'SUMMARY OF ERRORS THAT OCCURRED',
                '=======================================================================',
                '</em>',
            ]);

            foreach ($packagesWithError as $package) {
                $io->preparePackageHeader($package, "Package {package} error occurred during <em>{$package->getErrorDuring()}</em>:");
                $io->important()->info($package->getError());
            }
        }
    }
}
