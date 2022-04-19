<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Release;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\YiiDevTool\App\Component\Console\OutputManager;
use Yiisoft\YiiDevTool\App\Component\Console\YiiDevToolStyle;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\App\Component\Package\PackageErrorList;
use Yiisoft\YiiDevTool\App\Component\Package\PackageList;
use Yiisoft\YiiDevTool\Infrastructure\Composer\ComposerPackage;
use Yiisoft\YiiDevTool\Infrastructure\Composer\Config\ComposerConfig;

use function array_key_exists;

final class WhatCommand extends Command
{
    private ?OutputManager $io;
    private ?PackageList $packageList;

    protected function configure()
    {
        $this
            ->setName('release/what')
            ->setDescription('Find out what to release next');

        parent::configure();
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
            $this->packageList = new PackageList(
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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initPackageList();
        $io = $this->getIO();

        $packagesWithoutRelease = [];

        $installedPackages = $this->getPackageList()->getInstalledPackages();

        // Get packages without release.
        foreach ($installedPackages as $installedPackage) {
            if ($this->hasRelease($installedPackage)) {
                $io->info("Skip {$installedPackage->getName()}. Already released.");
                // Skip released packages.
                continue;
            }

            if (!$installedPackage->composerConfigFileExists()) {
                $io->info("Skip {$installedPackage->getName()}. No composer.json.");
                // Skip packages without composer.json.
                continue;
            }

            $packagesWithoutRelease[$installedPackage->getName()] = [
                'dependencies' => 0,
                'dependents' => 0,
            ];
        }

        // Get dependency stats for packages without release.
        foreach ($installedPackages as $installedPackage) {
            if (!array_key_exists($installedPackage->getName(), $packagesWithoutRelease)) {
                // Skip released packages and packages without composer.json.
                continue;
            }

            foreach ($this->getDependencyNames($installedPackage) as $dependencyName) {
                if (!array_key_exists($dependencyName, $packagesWithoutRelease)) {
                    // Skip released and third party packages.
                    continue;
                }

                $packagesWithoutRelease[$dependencyName]['dependents']++;
                $packagesWithoutRelease[$installedPackage->getName()]['dependencies']++;
            }
        }

        uasort(
            $packagesWithoutRelease,
            static fn($a, $b) => [$a['dependencies'], -$a['dependents']] <=> [$b['dependencies'], -$b['dependents']]
        );

        $packagesToRelease = [];
        $packagesToDevelop = [];
        foreach ($packagesWithoutRelease as $packageName => $stats) {
            if ($stats['dependencies'] > 0) {
                $packagesToDevelop[] = $packageName;
            } else {
                $packagesToRelease[] = $packageName;
            }
        }

        $io->important()->info('Packages to release:');
        $io->important()->newLine();

        foreach ($packagesToRelease as $packageName) {
            $stats = $packagesWithoutRelease[$packageName];
            $message = "[{$stats['dependencies']}/{$stats['dependents']}] $packageName";
            $io->important()->success($message);
        }

        $io->important()->info('Packages to develop:');
        $io->important()->newLine();

        foreach ($packagesToDevelop as $packageName) {
            $stats = $packagesWithoutRelease[$packageName];
            $message = "[{$stats['dependencies']}/{$stats['dependents']}] $packageName";
            $io->important()->error($message);
        }

        $io->important()->info(<<<TEXT
        [N/M] – an indicator, where
        N – count unreleased packages from which the package depends
        M – count unreleased packages which depends on the package
        TEXT
        );

        return Command::SUCCESS;
    }

    private function hasRelease(Package $package): bool
    {
        $gitWorkingCopy = $package->getGitWorkingCopy();
        foreach ($gitWorkingCopy->tags()->all() as $tag) {
            if ($tag !== '') {
                return true;
            }
        }
        return false;
    }

    private function getPackageList(): PackageList
    {
        return $this->packageList;
    }

    private function getDependencyNames(Package $package): array
    {
        $composerPackage = new ComposerPackage($package->getName(), $package->getPath());
        $composerConfig = $composerPackage->getComposerConfig();

        $names = [];
        $dependencyList = $composerConfig->getDependencyList(ComposerConfig::SECTION_REQUIRE);
        $devDependenciesList = $composerConfig->getDependencyList(ComposerConfig::SECTION_REQUIRE_DEV);

        foreach ($dependencyList->getDependencies() as $dependency) {
            $names[] = $dependency->getPackageName();
        }

        foreach ($devDependenciesList->getDependencies() as $dependency) {
            $names[] = $dependency->getPackageName();
        }

        return $names;
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
        return rtrim($this->getApplication()->getRootDir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
}
