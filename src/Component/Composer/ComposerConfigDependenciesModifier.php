<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Component\Composer;

use InvalidArgumentException;

class ComposerConfigDependenciesModifier
{
    /**
     * @var ComposerConfig
     */
    private ComposerConfig $config;

    public function __construct(ComposerConfig $config)
    {
        $this->config = $config;
    }

    public function removeDependencies(array $packages): self
    {
        $packageNamesForRemove = $this->getPackageNames($packages);

        $config = $this->config;

        $sections = [
            ComposerConfig::SECTION_REQUIRE,
            ComposerConfig::SECTION_REQUIRE_DEV,
        ];

        foreach ($sections as $section) {
            if ($config->hasSection($section)) {
                $packages = $config->getSection($section);

                foreach ($packages as $packageName => $packageVersion) {
                    if (in_array($packageName, $packageNamesForRemove, true)) {
                        unset($packages[$packageName]);
                    }
                }

                $config->setSection($section, $packages);
            }
        }

        return $this;
    }

    public function ensureDependenciesUsedOnlyInSection(array $packages, string $targetSection): self
    {
        if (!in_array($targetSection, [ComposerConfig::SECTION_REQUIRE, ComposerConfig::SECTION_REQUIRE_DEV], true)) {
            throw new InvalidArgumentException("$targetSection must be 'require' or 'require-dev'");
        }

        $sectionForCleaning =
            $targetSection === ComposerConfig::SECTION_REQUIRE ?
            ComposerConfig::SECTION_REQUIRE_DEV :
            ComposerConfig::SECTION_REQUIRE;

        $packageNamesForTargetSection = $this->getPackageNames($packages);

        $config = $this->config;

        if ($config->hasSection($sectionForCleaning)) {
            $packages = $config->getSection($sectionForCleaning);

            foreach ($packages as $packageName => $packageVersion) {
                if (in_array($packageName, $packageNamesForTargetSection, true)) {
                    unset($packages[$packageName]);
                }
            }

            $config->setSection($sectionForCleaning, $packages);
        }

        $targetSectionPackages =
            $config->hasSection($targetSection) ?
                $config->getSection($targetSection) : [];

        foreach ($packageNamesForTargetSection as $packageNameForTargetSection) {
            if (!array_key_exists($packageNameForTargetSection, $targetSectionPackages)) {
                // TODO: Can we use something instead of 'dev-master'?
                $targetSectionPackages[$packageNameForTargetSection] = 'dev-master';
            }
        }

        if (count($targetSectionPackages) > 0) {
            $config->setSection($targetSection, $targetSectionPackages);
        }

        return $this;
    }

    private function getPackageNames(array $packages): array
    {
        $packageNames = [];

        foreach ($packages as $package) {
            $packageNames[] = $package instanceof ComposerPackage ? $package->getName() : $package;
        }

        return $packageNames;
    }
}
