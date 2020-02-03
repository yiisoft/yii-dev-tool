<?php

namespace Yiisoft\YiiDevTool\Component\Package;

class ReplicationSource
{
    private string $packageId;
    private array $sourceFiles;

    public function __construct(string $packageId, array $sourceFiles)
    {
        $this->packageId = $packageId;
        $this->sourceFiles = $sourceFiles;
    }

    public function getPackageId(): string
    {
        return $this->packageId;
    }

    public function getSourceFiles(): array
    {
        return $this->sourceFiles;
    }
}
