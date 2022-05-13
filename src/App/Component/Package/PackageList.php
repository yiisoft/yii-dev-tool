<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Component\Package;

use function array_key_exists;

class PackageList
{
    /** @var Package[] */
    private array $list = [];

    /** @var null|Package[] */
    private ?array $installedList = null;

    /** @var null|Package[] */
    private ?array $installedAndEnabledList = null;

    public function __construct(string $configFile, string $packagesRootDir)
    {
        /** @noinspection PhpIncludeInspection */
        $config = require $configFile;

        foreach ($config as $packageId => $packageConfig) {
            $this->list[$packageId] = new Package($packageId, $packageConfig, $packagesRootDir);
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
                static fn(Package $package) => $package->enabled(),
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
}
