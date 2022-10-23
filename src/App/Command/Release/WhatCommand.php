<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Release;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\YiiDevTool\App\Component\Console\OutputManager;
use Yiisoft\YiiDevTool\App\Component\Console\YiiDevToolStyle;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\App\Component\Package\PackageList;
use Yiisoft\YiiDevTool\App\YiiDevToolApplication;
use Yiisoft\YiiDevTool\Infrastructure\Composer\ComposerPackage;
use Yiisoft\YiiDevTool\Infrastructure\Composer\Config\ComposerConfig;

use function array_key_exists;

/** @method YiiDevToolApplication getApplication()  **/
final class WhatCommand extends Command
{
    private ?OutputManager $io = null;
    private ?PackageList $packageList = null;

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
                $this
                    ->getApplication()
                    ->getConfig()
            );
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());

            exit(1);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initPackageList();
        $io = $this->getIO();

        $packagesWithoutRelease = [];

        $installedPackages = $this
            ->getPackageList()
            ->getInstalledAndEnabledPackages();

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
                'deps' => [],
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
                $packagesWithoutRelease[$dependencyName]['deps'][] = $this->removeVendorName($installedPackage->getName());
                $packagesWithoutRelease[$installedPackage->getName()]['dependencies']++;
                $packagesWithoutRelease[$installedPackage->getName()]['deps'][] = $this->removeVendorName($dependencyName);
            }
        }

        uasort(
            $packagesWithoutRelease,
            static fn ($a, $b) => [$a['dependencies'], -$a['dependents']] <=> [$b['dependencies'], -$b['dependents']]
        );

        $successStyle = new TableCellStyle(['fg' => 'green']);
        $errorStyle = new TableCellStyle(['fg' => 'red']);
        $packagesToRelease = [];
        $packagesToDevelop = [];

        foreach ($packagesWithoutRelease as $packageName => $stats) {
            if ($stats['dependencies'] > 0) {
                $packagesToDevelop[] = [
                    new TableCell($packageName, ['style' => $errorStyle]),
                    $stats['dependencies'],
                    $stats['dependents'],
                    $this->concatDependencies($stats['deps']),
                ];
            } else {
                $packagesToRelease[] = [
                    new TableCell($packageName, ['style' => $successStyle]),
                    $stats['dependencies'],
                    $stats['dependents'],
                    $this->concatDependencies($stats['deps']),
                ];
            }
        }

        $tableIO = new Table($output);
        $tableIO->setHeaders(['Package', 'Out deps', 'In deps', 'Packages']);
        $tableIO->setColumnMaxWidth(3, 120);

        if (count($packagesToRelease) > 0) {
            $tableIO->addRow([
                new TableCell('Packages to release', [
                    'colspan' => 4,
                    'style' => new TableCellStyle(['align' => 'center', 'bg' => 'green']),
                ]),
            ]);
            $tableIO->addRows($packagesToRelease);
        }
        if (count($packagesToDevelop) > 0) {
            $tableIO->addRow([
                new TableCell('Packages to develop', [
                    'colspan' => 4,
                    'style' => new TableCellStyle(['align' => 'center', 'bg' => 'red']),
                ]),
            ]);
            $tableIO->addRows($packagesToDevelop);
        }

        $tableIO->render();

        $io
            ->important()
            ->info(
                <<<TEXT
        <success>Out deps</success> – count unreleased packages from which the package depends
        <success>In deps</success> – count unreleased packages which depends on the package
        TEXT
            );

        return Command::SUCCESS;
    }

    private function hasRelease(Package $package): bool
    {
        $gitWorkingCopy = $package->getGitWorkingCopy();
        foreach ($gitWorkingCopy
                     ->tags()
                     ->all() as $tag) {
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

        return array_unique($names);
    }

    private function removeVendorName(string $packageName): string|array
    {
        return preg_replace('/^[a-z0-9][a-z0-9-]*[a-z0-9]\//i', '', $packageName);
    }

    private function concatDependencies($deps): string
    {
        return implode("\n", array_map(fn (array $array) => implode(', ', $array), array_chunk($deps, 7)));
    }
}
