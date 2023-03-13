<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Component\Package;

class ReplicationSet
{
    public function __construct(private string $sourcePackage, private array $files, private array $includedPackages, private array $excludedPackages)
    {
    }

    public function getSourcePackage(): string
    {
        return $this->sourcePackage;
    }

    public function getFiles(): array
    {
        return $this->files;
    }

    public function appliesToPackage(string $name): bool
    {
        if (!in_array('*', $this->includedPackages, true) && !in_array($name, $this->includedPackages, true)) {
            return false;
        }

        return !(in_array('*', $this->excludedPackages, true) || in_array($name, $this->excludedPackages, true))



        ;
    }
}
