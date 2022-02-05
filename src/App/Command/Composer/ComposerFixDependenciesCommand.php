<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Composer;

use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\Infrastructure\CodeUsage\CodeUsageEnvironment;
use Yiisoft\YiiDevTool\Infrastructure\CodeUsage\NamespaceUsageFinder;
use Yiisoft\YiiDevTool\Infrastructure\Composer\ComposerInstallation;
use Yiisoft\YiiDevTool\Infrastructure\Composer\ComposerPackage;
use Yiisoft\YiiDevTool\Infrastructure\Composer\ComposerPackageUsageAnalyzer;
use Yiisoft\YiiDevTool\Infrastructure\Composer\Config\ComposerConfig;
use Yiisoft\YiiDevTool\Infrastructure\Composer\Config\ComposerConfigDependenciesModifier;

final class ComposerFixDependenciesCommand extends PackageCommand
{
    private array $skippedPackageIds = [];

    private const DEV_PATHS = [
        'tests',
        'config/params-test.php',
        'config/tests.php',
        'public/index-test.php',
    ];

    private const PRODUCTION_PATHS = [
        'config',
        'src',
        'public/index.php',
    ];

    protected function configure(): void
    {
        $this
            ->setName('composer/fix-dependencies')
            ->setDescription('Fix <fg=yellow;options=bold>require</> and <fg=yellow;options=bold>require-dev</> sections in <fg=blue;options=bold>composer.json</> according to the actual use of classes');

        parent::configure();
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        $message = 'Nothing to fix.';

        $skippedCount = count($this->skippedPackageIds);

        if ($skippedCount > 0) {
            $justOne = ($skippedCount === 1);

            $message .= sprintf(
                "\n\nPackage%s %s %s skipped.\nPlease use verbose mode to see details.",
                $justOne ? '' : 's',
                implode(',', $this->skippedPackageIds),
                $justOne ? 'is' : 'are',
            );
        }

        return $message;
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, "Fixing package {package} dependencies");

        if (!$package->composerConfigFileExists()) {
            $io->warning([
                "No <file>composer.json</file> in package <package>{$package->getName()}</package>.",
                'Dependencies fixing skipped.',
            ]);

            return;
        }

        $composerPackage = new ComposerPackage($package->getName(), $package->getPath());
        $composerInstallation = new ComposerInstallation($composerPackage);

        if ($composerInstallation->hasNotInstalledDependencyPackages()) {
            $notInstalledDependencyPackages = $composerInstallation->getNotInstalledDependencyPackages();

            $message = count($notInstalledDependencyPackages) === 1 ? 'Dependency' : 'Dependencies';
            foreach ($notInstalledDependencyPackages as $notInstalledDependencyPackage) {
                $message .= " <package>{$notInstalledDependencyPackage->getName()}</package>";
            }
            $message .= ' is not installed.';

            $this->skippedPackageIds[] = $package->getId();

            $io->warning([
                $message,
                'Dependencies fixing skipped.',
            ]);

            return;
        }

        $dependencyPackages = $composerInstallation->getInstalledDependencyPackages();

        $namespaceUsages =
            (new NamespaceUsageFinder())
            ->addTargetPaths(CodeUsageEnvironment::DEV, self::DEV_PATHS, $composerPackage->getPath())
            ->addTargetPaths(CodeUsageEnvironment::PRODUCTION, self::PRODUCTION_PATHS, $composerPackage->getPath())
            ->getUsages();

        /**
         * TODO: Ignore autoload-dev namespaces of dependencies during analysis.
         * Section "autoload-dev" is significant only for the root package.
         */
        $analyzer = new ComposerPackageUsageAnalyzer($dependencyPackages, $namespaceUsages);
        $analyzer->analyze();

        $composerConfig = $composerPackage->getComposerConfig();

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
            $io->info('Nothing to fix.')->newLine();
            return;
        }

        $composerConfig->writeToFile($composerPackage->getComposerConfigPath());
        $io->important()->success('✔ Composer config fixed.');
    }
}
