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
            foreach ($package->getPSRNamespaces() as $packageNamespace) {
                foreach ($this->namespaceUsages as $namespaceUsage) {
                    if (str_starts_with($namespaceUsage->getIdentifier(), "\\$packageNamespace")) {
                        $this->registerPackageUsage($package->getName(), $namespaceUsage->getEnvironments());
                    }
                }
            }
        }
    }

    /**
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
            /**
             * TODO: Implement support of packages that uses non-PSR autoload.
             * It's difficult, but possible.
             *
             * For now, just skip them, because we don't know exactly
             * if their dependencies are being used or not.
             */
            if ($package->usesNonPSRAutoload()) {
                continue;
            }

            /**
             * TODO: Implement notices about packages that provide binaries.
             *
             * Such packages can be used as hand tools, so they cannot be removed automatically.
             * They should be checked by a human.
             */
            if ($package->providesBinaries()) {
                continue;
            }

            /**
             * TODO: Implement notices about plugins.
             * They should be checked by a human.
             */
            if ($package->isComposerPlugin()) {
                continue;
            }

            $packageName = $package->getName();

            if ($this->hasPackageUsage($packageName)) {
                continue;
            }

            $result[] = $package->getName();
        }

        return $result;
    }

    /**
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
