<?php

namespace yiidev\components\package;

class Package
{
    private $name;
    private $directoryName;
    private $path;

    private $error;

    public function __construct(string $name, string $directoryName, string $baseDirectoryPath)
    {
        $this->name = $name;
        $this->directoryName = $directoryName;
        $this->path = $baseDirectoryPath . DIRECTORY_SEPARATOR . $directoryName;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDirectoryName(): string
    {
        return $this->directoryName;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setError(string $error): void
    {
        $this->error = $error;
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    public function getError(): ?string
    {
        return $this->error;
    }
}
