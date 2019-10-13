<?php

namespace yiidev\components\package;

class ReplicationSource extends Package
{
    private $sourceFiles;

    public function __construct(string $name, string $directoryName, string $baseDirectoryPath, array $sourceFiles)
    {
        parent::__construct($name, $directoryName, $baseDirectoryPath);
        $this->sourceFiles = $sourceFiles;
    }

    public function getSourceFiles(): ?array
    {
        return $this->sourceFiles;
    }
}
