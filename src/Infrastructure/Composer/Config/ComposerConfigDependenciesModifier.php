<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Infrastructure\Composer\Config;

use InvalidArgumentException;

class ComposerConfigDependenciesModifier
{
    public function __construct(private ComposerConfig $config)
    {
    }

    /**
     * Remove dependencies from composer config.
     *
     * @param string[] $packageNames Names of packages to be removed.
     * @param string|null $targetSection Section from which dependencies should be removed: "require" or "require-dev".
     * If NULL, dependencies will be removed from both sections.
     *
     * @return $this
     */
    public function removeDependencies(array $packageNames, ?string $targetSection = null): self
    {
        $this->validatePackageNames($packageNames);

        if ($targetSection === null) {
            $sections = ComposerConfig::getAllDependencySections();
        } else {
            ComposerConfig::validateDependencySection($targetSection);
            $sections = [$targetSection];
        }

        $config = $this->config;

        foreach ($sections as $section) {
            if ($config->hasSection($section)) {
                $dependencyList = $config
                    ->getDependencyList($section)
                    ->removeDependencies($packageNames);

                if ($dependencyList->isEmpty()) {
                    $config->removeSection($section);
                } else {
                    $config->setDependencyList($section, $dependencyList);
                }
            }
        }

        return $this;
    }

    public function ensureDependenciesUsedOnlyInSection(array $packageNames, string $targetSection): self
    {
        $this->validatePackageNames($packageNames);
        ComposerConfig::validateDependencySection($targetSection);

        $sectionForCleaning = $targetSection === ComposerConfig::SECTION_REQUIRE ?
            ComposerConfig::SECTION_REQUIRE_DEV :
            ComposerConfig::SECTION_REQUIRE;

        $config = $this->config;

        $targetDependencyList = $config->getDependencyList($targetSection);
        $dependenciesChanged = false;
        foreach ($packageNames as $packageName) {
            if (!$targetDependencyList->hasDependency($packageName)) {
                $targetDependencyList->addDependency(
                    $packageName,
                    $this->getDependencyConstraint($packageName, $sectionForCleaning)
                );

                $dependenciesChanged = true;
            }
        }

        if ($dependenciesChanged) {
            if ($config->sortPackagesEnabled()) {
                $targetDependencyList->sort();
            }

            $config->setDependencyList($targetSection, $targetDependencyList);
        }

        $this->removeDependencies($packageNames, $sectionForCleaning);

        return $this;
    }

    private function validatePackageNames(array $packageNames): void
    {
        foreach ($packageNames as $packageName) {
            if (!is_string($packageName)) {
                throw new InvalidArgumentException('Package names must be an array of strings.');
            }
        }
    }

    private function getDependencyConstraint(string $packageName, string $section): string
    {
        $dependencyList = $this->config->getDependencyList($section);

        if ($dependencyList->hasDependency($packageName)) {
            return $dependencyList
                ->getDependency($packageName)
                ->getConstraint();
        }

        return 'dev-master';
    }
}
