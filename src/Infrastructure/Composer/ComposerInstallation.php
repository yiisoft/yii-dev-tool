<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Infrastructure\Composer;

use Yiisoft\YiiDevTool\Infrastructure\Composer\Config\ComposerConfig;

class ComposerInstallation
{
    private ComposerPackage $rootPackage;
    private array $installedDependencies;
    private array $notInstalledDependencies;

    public function __construct(ComposerPackage $rootPackage)
    {
        $this->rootPackage = $rootPackage;
        $this->analyzeDependencies();
    }

    public function hasNotInstalledDependencyPackages(): bool
    {
        return count($this->notInstalledDependencies) > 0;
    }

    /**
     * @return ComposerPackage[]
     */
    public function getNotInstalledDependencyPackages(): array
    {
        return $this->notInstalledDependencies;
    }

    /**
     * @return ComposerPackage[]
     */
    public function getInstalledDependencyPackages(): array
    {
        return $this->installedDependencies;
    }

    /**
     * @param string $section
     * @return ComposerPackage[]
     */
    public function getDependencyPackages(string $section): array
    {
        ComposerConfig::validateDependencySection($section);

        $rootPackage = $this->rootPackage;
        $dependencies = $this->rootPackage->getComposerConfig()->getDependencyList($section)->getDependencies();

        $packages = [];
        foreach ($dependencies as $dependency) {
            if ($dependency->getPackageName() === 'roave/security-advisories') {
                // Skip special case because this is a virtual package
                continue;
            }

            if ($dependency->isPlatformRequirement()) {
                // Skip platform requirements
                continue;
            }

            $packageName = $dependency->getPackageName();
            $packages[] = new ComposerPackage($packageName, "{$rootPackage->getPath()}/vendor/{$packageName}");
        }

        return $packages;
    }

    private function analyzeDependencies(): void
    {
        /** @var ComposerPackage[] $dependencies */
        $dependencies = array_merge(
            $this->getDependencyPackages(ComposerConfig::SECTION_REQUIRE),
            $this->getDependencyPackages(ComposerConfig::SECTION_REQUIRE_DEV),
        );

        /** @var ComposerPackage[] $installed */
        $installed = [];

        /** @var ComposerPackage[] $notInstalled */
        $notInstalled = [];

        foreach ($dependencies as $dependency) {
            if ($dependency->composerConfigFileExists()) {
                $installed[] = $dependency;
            } else {
                $notInstalled[] = $dependency;
            }
        }

        // Filter virtual packages provided by installed packages
        foreach ($notInstalled as $notInstalledIndex => $notInstalledPackage) {
            foreach ($installed as $installedPackage) {
                if ($installedPackage->doesProvidePackage($notInstalledPackage->getName())) {
                    unset($notInstalled[$notInstalledIndex]);
                    break;
                }
            }
        }

        $this->installedDependencies = $installed;
        $this->notInstalledDependencies = $notInstalled;
    }
}
