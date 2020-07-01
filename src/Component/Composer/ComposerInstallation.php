<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Component\Composer;

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

    public function hasNotInstalledDependencies(): bool
    {
        return count($this->notInstalledDependencies) > 0;
    }

    /**
     * @return ComposerPackage[]
     */
    public function getNotInstalledDependencies(): array
    {
        return $this->notInstalledDependencies;
    }

    /**
     * @return ComposerPackage[]
     */
    public function getNonVirtualInstalledDependencies(): array
    {
        return $this->installedDependencies;
    }

    private function analyzeDependencies(): void
    {
        $dependencies = $this->rootPackage->getDependencyPackages();

        $installed = [];
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
