<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Infrastructure\Composer;

use InvalidArgumentException;
use RuntimeException;
use Yiisoft\YiiDevTool\Infrastructure\CodeUsage\CodeUsage;

class ComposerPackageUsageAnalyzer
{
    /**
     * @var ComposerPackage[]
     */
    private array $packages = [];

    /**
     * @var CodeUsage[]
     */
    private array $namespaceUsages = [];

    /**
     * @var CodeUsage[]
     */
    private array $packageUsages = [];

    /**
     * @param ComposerPackage[] $packages
     * @param CodeUsage[] $namespaceUsages
     */
    public function __construct(array $packages, array $namespaceUsages)
    {
        foreach ($packages as $package) {
            if (!$package instanceof ComposerPackage) {
                throw new InvalidArgumentException('$packages must be an array of ComposerPackage objects.');
            }
        }

        foreach ($namespaceUsages as $namespaceUsage) {
            if (!$namespaceUsage instanceof CodeUsage) {
                throw new InvalidArgumentException('$namespaceUsages must be an array of CodeUsage objects.');
            }
        }

        foreach ($packages as $package) {
            $this->packages[$package->getName()] = $package;
        }

        foreach ($namespaceUsages as $namespaceUsage) {
            $this->namespaceUsages[$namespaceUsage->getIdentifier()] = $namespaceUsage;
        }
    }

    public function analyze(): void
    {
        foreach ($this->packages as $package) {
            foreach ($package->getNamespaces() as $packageNamespace) {
                foreach ($this->namespaceUsages as $namespaceUsage) {
                    if (strpos($namespaceUsage->getIdentifier(), "\\$packageNamespace") === 0) {
                        $this->registerPackageUsage($package->getName(), $namespaceUsage->getEnvironments());
                    }
                }
            }
        }
    }

    /**
     * @param string $environment
     * @return string[] array of package names.
     */
    public function getNamesOfPackagesUsedInSpecifiedEnvironment(string $environment): array
    {
        $result = [];

        foreach ($this->packageUsages as $packageUsage) {
            if ($packageUsage->usedInEnvironment($environment)) {
                $result[] = $this->packages[$packageUsage->getIdentifier()]->getName();
            }
        }

        return $result;
    }

    /**
     * @param string $environment
     * @return string[] array of package names.
     */
    public function getNamesOfPackagesUsedOnlyInSpecifiedEnvironment(string $environment): array
    {
        $result = [];

        foreach ($this->packageUsages as $packageUsage) {
            if ($packageUsage->usedOnlyInSpecifiedEnvironment($environment)) {
                $result[] = $this->packages[$packageUsage->getIdentifier()]->getName();
            }
        }

        return $result;
    }

    /**
     * @return string[] array of package names.
     */
    public function getUnusedPackageNames(): array
    {
        $result = [];

        foreach ($this->packages as $package) {
            $packageName = $package->getName();

            if ($this->hasPackageUsage($packageName)) {
                $packageUsage = $this->getPackageUsage($packageName);

                if ($packageUsage->used()) {
                    continue;
                }
            }

            $result[] = $package->getName();
        }

        return $result;
    }

    /**
     * @param string $packageName
     * @param string[] $environments
     */
    private function registerPackageUsage(string $packageName, array $environments): void
    {
        if (!array_key_exists($packageName, $this->packageUsages)) {
            $this->packageUsages[$packageName] = new CodeUsage($packageName, $environments);
        } else {
            $this->packageUsages[$packageName]->registerUsageInEnvironments($environments);
        }
    }

    private function hasPackageUsage(string $packageName): bool
    {
        return array_key_exists($packageName, $this->packageUsages);
    }

    private function getPackageUsage(string $packageName): CodeUsage
    {
        if (!$this->hasPackageUsage($packageName)) {
            throw new RuntimeException('There is no such package usage.');
        }

        return $this->packageUsages[$packageName];
    }
}
