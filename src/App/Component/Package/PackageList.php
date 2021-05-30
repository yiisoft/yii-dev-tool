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
                if ($package->enabled() && file_exists($package->getPath())) {
                    $this->installedList[$id] = $package;
                }
            }
        }

        return $this->installedList;
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
