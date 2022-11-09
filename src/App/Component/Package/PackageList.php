<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Component\Package;

use function array_key_exists;

class PackageList
{
    /** @var Package[] */
    private array $list = [];

    /** @var Package[]|null */
    private ?array $installedList = null;

    /** @var Package[]|null */
    private ?array $installedAndEnabledList = null;

    public function __construct(string $ownerPackages, string $configFile, string $packagesRootDir)
    {
        /** @noinspection PhpIncludeInspection */
        $config = require $configFile;

        foreach ($config as $rootPackageId => $packageConfig) {
            $this->list[$rootPackageId] = new Package($rootPackageId, $packageConfig, $ownerPackages, $packagesRootDir, null);

            if (is_array($packageConfig)) {
                $isPackageMonorepo = (bool) ($packageConfig['monorepo'] ?? false);
                if ($isPackageMonorepo) {
                    foreach ($packageConfig['packages'] as $packageId => $packageConfig) {
                        $index = "{$rootPackageId}__{$packageId}";
                        $this->list[$index] = new Package($packageId, $packageConfig, $ownerPackages, $packagesRootDir, $this->list[$rootPackageId]);
                    }
                }
            }
        }
    }

    /**
     * @return Package[]
     */
    public function getAllPackages(): array
    {
        return $this->list;
    }

    public function hasPackage(string $packageId): bool
    {
        return array_key_exists($packageId, $this->list);
    }

    public function getPackage(string $packageId): ?Package
    {
        return $this->hasPackage($packageId) ? $this->list[$packageId] : null;
    }

    /**
     * @return Package[]
     */
    public function getInstalledPackages(): array
    {
        if ($this->installedList === null) {
            $this->installedList = [];

            foreach ($this->list as $id => $package) {
                if (file_exists($package->getPath())) {
                    $this->installedList[$id] = $package;
                }
            }
        }

        return $this->installedList;
    }

    /**
     * @return Package[]
     */
    public function getInstalledAndEnabledPackages(): array
    {
        if ($this->installedAndEnabledList === null) {
            $this->installedAndEnabledList = array_filter(
                $this->getInstalledPackages(),
                static fn (Package $package) => $package->enabled(),
            );
        }

        return $this->installedAndEnabledList;
    }

    /**
     * @return Package[]
     */
    public function getEnabledPackages(): array
    {
        $packages = [];

        foreach ($this->list as $id => $package) {
            if ($package->enabled()) {
                $packages[$id] = $package;
            }
        }

        return $packages;
    }

    public function getTree(): array
    {
        $result = [];
        foreach ($this->list as $package) {
            if ($package->isVirtual()) {
                $rootPackage = $package->getRootPackage();
                if ($rootPackage !== null) {
                    $config = $result[$rootPackage->getId()] ?? true;
                    if (is_bool($config)) {
                        $config = [
                            'enabled' => $config,
                            'monorepo' => true,
                            'packages' => [
                                $package->getId() => $package->enabled(),
                            ],
                        ];
                    } elseif (is_array($config)) {
                        $config['packages'][$package->getId()] =  $package->enabled();
                    }

                    $result[$rootPackage->getId()] = $config;
                    continue;
                }
            }
            $config = $result[$package->getId()] ?? $package->enabled();
            if (is_array($config)) {
                $config = array_merge([
                    'enabled' => $package->enabled(),
                    'monorepo' => true,
                    'packages' => [],
                ], $config);
            }

            $result[$package->getId()] = $config;

        }
        return  $result;
    }
}
