<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Command;

use Yiisoft\YiiDevTool\Component\CodeUsage\ComposerPackageUsageAnalyzer;
use Yiisoft\YiiDevTool\Component\Composer\ComposerConfig;
use Yiisoft\YiiDevTool\Component\Composer\ComposerConfigDependenciesModifier;
use Yiisoft\YiiDevTool\Component\Composer\ComposerInstallation;
use Yiisoft\YiiDevTool\Component\Composer\ComposerPackage;
use Yiisoft\YiiDevTool\Component\CodeUsage\CodeUsageEnvironment;
use Yiisoft\YiiDevTool\Component\CodeUsage\NamespaceUsageFinder;
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
        return '<success>✔ Nothing to fix</success>';
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, "Fixing package {package} dependencies");

        $package = new ComposerPackage($package->getName(), $package->getPath());

        if (!$package->composerConfigFileExists()) {
            $io->warning([
                "No <file>composer.json</file> in package <package>{$package->getName()}</package>.",
                "Dependencies fixing skipped.",
            ]);

            return;
        }

        $composerInstallation = new ComposerInstallation($package);

        if ($composerInstallation->hasNotInstalledDependencies()) {
            $notInstalledDependencyPackages = $composerInstallation->getNotInstalledDependencies();

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

        $dependencyPackages = $composerInstallation->getNonVirtualInstalledDependencies();

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

        (new ComposerConfigDependenciesModifier($composerConfig))
            ->removeDependencies($analyzer->getUnusedPackages())
            ->ensureDependenciesUsedOnlyInSection(
                $analyzer->getPackagesUsedInSpecifiedEnvironment(CodeUsageEnvironment::PRODUCTION),
                ComposerConfig::SECTION_REQUIRE,
            )
            ->ensureDependenciesUsedOnlyInSection(
                $analyzer->getPackagesUsedOnlyInSpecifiedEnvironment(CodeUsageEnvironment::DEV),
                ComposerConfig::SECTION_REQUIRE_DEV,
            );

        $composerConfig->writeToFile($package->getComposerConfigPath());

        $io->done();
    }
}
