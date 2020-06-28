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
        $sections = [
            ComposerConfig::SECTION_REQUIRE,
            ComposerConfig::SECTION_REQUIRE_DEV,
        ];

        foreach ($sections as $section) {
            $this->removeDependenciesFromConfig($section, $this->getPackageNames($packages));
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
        $originalDependencies = $config->getDependencies();

        $this->removeDependenciesFromConfig($sectionForCleaning, $packageNamesForTargetSection);

        $targetSectionPackages =
            $config->hasSection($targetSection) ?
                $config->getSection($targetSection) : [];

        $originalTargetSectionPackages = $targetSectionPackages;

        foreach ($packageNamesForTargetSection as $packageNameForTargetSection) {
            if (!array_key_exists($packageNameForTargetSection, $targetSectionPackages)) {
                $targetSectionPackages[$packageNameForTargetSection] =
                    $originalDependencies[$packageNameForTargetSection] ?? 'dev-master';
            }
        }

        if (!$this->dependencyListsAreEqual($originalTargetSectionPackages, $targetSectionPackages)) {
            if ($config->sortPackagesEnabled()) {
                $targetSectionPackages = $this->sortDependencyListForComposerConfig($targetSectionPackages);
            }

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

    private function removeDependenciesFromConfig(string $section, array $packageNamesToBeDeleted): void
    {
        $config = $this->config;

        if (!$config->hasSection($section)) {
            return;
        }

        $packages = $config->getSection($section);

        foreach ($packages as $packageName => $packageVersion) {
            if (in_array($packageName, $packageNamesToBeDeleted, true)) {
                unset($packages[$packageName]);
            }
        }

        if (count($packages) > 0) {
            $config->setSection($section, $packages);
        } else {
            $config->removeSection($section);
        }
    }

    private function dependencyListsAreEqual(array $firstDependencyList, array $secondDependencyList): bool
    {
        ksort($firstDependencyList);
        ksort($secondDependencyList);

        return $firstDependencyList === $secondDependencyList;
    }

    /**
     * The real sorting algorithm is more complicated:
     * https://github.com/composer/composer/blob/ec9ca9e7398229d6162c0d5e8b64990175476335/src/Composer/Json/JsonManipulator.php#L110-L146
     *
     * We use here a simplified version of the sorting algorithm.
     *
     * @param array $dependencies Unsorted list of composer dependencies.
     * @return array Sorted list.
     */
    private function sortDependencyListForComposerConfig(array $dependencies): array
    {
        $extensions = [];
        $sorted = [];

        // 'php' must be the first
        foreach ($dependencies as $name => $version) {
            if ($name === 'php') {
                $sorted[$name] = $version;
                unset($dependencies[$name]);
                break;
            }
        }

        // Looking for php extensions
        foreach ($dependencies as $name => $version) {
            if (strpos($name, 'ext-') === 0) {
                $extensions[$name] = $version;
                unset($dependencies[$name]);
            }
        }

        ksort($extensions);
        ksort($dependencies);

        $sorted += $extensions;
        $sorted += $dependencies;

        return $sorted;
    }
}
