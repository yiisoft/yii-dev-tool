<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Command;

use Yiisoft\YiiDevTool\Component\CodeUsage\CodeUsageEnvironment;
use Yiisoft\YiiDevTool\Component\CodeUsage\ComposerPackageUsageAnalyzer;
use Yiisoft\YiiDevTool\Component\CodeUsage\NamespaceUsageFinder;
use Yiisoft\YiiDevTool\Component\Composer\ComposerInstallation;
use Yiisoft\YiiDevTool\Component\Composer\ComposerPackage;
use Yiisoft\YiiDevTool\Component\Composer\Config\ComposerConfig;
use Yiisoft\YiiDevTool\Component\Composer\Config\ComposerConfigDependenciesModifier;
use Yiisoft\YiiDevTool\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\Component\Package\Package;

class FixDependenciesCommand extends PackageCommand
{
    protected function configure()
    {
        $this
            ->setName('fix-dependencies')
            ->setDescription('Fix <fg=yellow;options=bold>require</> and <fg=yellow;options=bold>require-dev</> sections in <fg=blue;options=bold>composer.json</> according to the actual use of classes');

        $this->addPackageArgument();
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return 'Nothing to fix.';
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, "Fixing package {package} dependencies");

        if (!$package->composerConfigFileExists()) {
            $io->warning([
                "No <file>composer.json</file> in package <package>{$package->getName()}</package>.",
                "Dependencies fixing skipped.",
            ]);

            return;
        }

        $package = new ComposerPackage($package->getName(), $package->getPath());
        $composerInstallation = new ComposerInstallation($package);

        if ($composerInstallation->hasNotInstalledDependencyPackages()) {
            $notInstalledDependencyPackages = $composerInstallation->getNotInstalledDependencyPackages();

            $message = count($notInstalledDependencyPackages) === 1 ? 'Dependency' : 'Dependencies';
            foreach ($notInstalledDependencyPackages as $notInstalledDependencyPackage) {
                $message .= " <package>{$notInstalledDependencyPackage->getName()}</package>";
            }
            $message .= " is not installed.";

            $io->warning([
                $message,
                "Dependencies fixing skipped.",
            ]);

            return;
        }

        $dependencyPackages = $composerInstallation->getInstalledDependencyPackages();

        $namespaceUsages =
            (new NamespaceUsageFinder())
            ->addTargetPaths(CodeUsageEnvironment::PRODUCTION, [
                'config/common.php',
                'config/web.php',
                'src',
            ], $package->getPath())
            ->addTargetPaths(CodeUsageEnvironment::DEV, [
                'config/tests.php',
                'tests',
            ], $package->getPath())
            ->getUsages();

        $analyzer = new ComposerPackageUsageAnalyzer($dependencyPackages, $namespaceUsages);
        $analyzer->analyze();

        $composerConfig = $package->getComposerConfig();

        $originalDependencyList = $composerConfig->getDependencyList(ComposerConfig::SECTION_REQUIRE);
        $originalDevDependencyList = $composerConfig->getDependencyList(ComposerConfig::SECTION_REQUIRE_DEV);

        (new ComposerConfigDependenciesModifier($composerConfig))
            ->removeDependencies($analyzer->getUnusedPackageNames())
            ->ensureDependenciesUsedOnlyInSection(
                $analyzer->getNamesOfPackagesUsedInSpecifiedEnvironment(CodeUsageEnvironment::PRODUCTION),
                ComposerConfig::SECTION_REQUIRE,
            )
            ->ensureDependenciesUsedOnlyInSection(
                $analyzer->getNamesOfPackagesUsedOnlyInSpecifiedEnvironment(CodeUsageEnvironment::DEV),
                ComposerConfig::SECTION_REQUIRE_DEV,
            );

        $currentDependencyList = $composerConfig->getDependencyList(ComposerConfig::SECTION_REQUIRE);
        $currentDevDependencyList = $composerConfig->getDependencyList(ComposerConfig::SECTION_REQUIRE_DEV);

        if ($originalDependencyList->isEqualTo($currentDependencyList)
            && $originalDevDependencyList->isEqualTo($currentDevDependencyList)) {
            $io->info("Nothing to fix.")->newLine();
            return;
        }

        $composerConfig->writeToFile($package->getComposerConfigPath());
        $io->important()->success("✔ Composer config fixed.");
    }
}
