<?php

namespace yiidev\components\package;

class ReplicationSource
{
    /** @var string */
    private $packageId;

    /** @var array */
    private $sourceFiles;

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
