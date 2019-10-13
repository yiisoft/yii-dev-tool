<?php

namespace yiidev\components\package;

class PackageList
{
    /** @var Package[] */
    private $list = [];

    /** @var null|Package[] */
    private $installedList;

    public function __construct(string $configFile, string $packagesBaseDirectoryPath)
    {
        /** @noinspection PhpIncludeInspection */
        $config = require $configFile;

        foreach ($config as $packageName => $packageDirectoryName) {
            $this->list[$packageName] = new Package($packageName, $packageDirectoryName, $packagesBaseDirectoryPath);
        }
    }

    /**
     * @return Package[]
     */
    public function getAllPackages(): array
    {
        return $this->list;
    }

    public function hasPackage(string $packageName): bool
    {
        return array_key_exists($packageName, $this->list);
    }

    public function getPackage(string $packageName): ?Package
    {
        return $this->hasPackage($packageName) ? $this->list[$packageName] : null;
    }

    /**
     * @return Package[]
     */
    public function getInstalledPackages(): array
    {
        if ($this->installedList === null) {
            $this->installedList = [];

            foreach ($this->list as $name => $package) {
                if (file_exists($package->getPath())) {
                    $this->installedList[$name] = $package;
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

        foreach ($this->list as $name => $package) {
            if ($package->hasError()) {
                $packages[$name] = $package;
            }
        }

        return $packages;
    }
}
