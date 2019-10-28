<?php

namespace yiidev\components\package;

class PackageList
{
    /** @var Package[] */
    private $list = [];

    /** @var null|Package[] */
    private $installedList;

    public function __construct(string $configFile)
    {
        /** @noinspection PhpIncludeInspection */
        $config = require $configFile;

        foreach ($config as $packageId => $packageConfig) {
            $this->list[$packageId] = new Package($packageId, $packageConfig);
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
    public function getPackagesWithError(): array
    {
        $packages = [];

        foreach ($this->list as $id => $package) {
            if ($package->hasError()) {
                $packages[$id] = $package;
            }
        }

        return $packages;
    }
}
