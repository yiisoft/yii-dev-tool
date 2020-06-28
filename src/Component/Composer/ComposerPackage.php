<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Component\Composer;

class ComposerPackage
{
    private string $name;
    private string $path;
    private ?ComposerConfig $config = null;

    public function __construct(string $name, string $path)
    {
        $this->name = $name;
        $this->path = $path;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function installed(): bool
    {
        return file_exists("{$this->path}/composer.lock");
    }

    public function getComposerConfigPath(): string
    {
        return "{$this->path}/composer.json";
    }

    public function getComposerConfig(): ComposerConfig
    {
        if ($this->config === null) {
            $this->config = ComposerConfig::createByFilePath($this->getComposerConfigPath());
        }

        return $this->config;
    }

    /**
     * @param string $specificVendor
     * @return static[]
     */
    public function getDependencyPackages(?string $specificVendor = null): array
    {
        $packages = [];
        $nonVirtualDependencies = $this->getComposerConfig()->getDependencies($specificVendor, true);

        foreach ($nonVirtualDependencies as $packageName => $packageVersion) {
            $packages[] = new static($packageName, "{$this->path}/vendor/{$packageName}");
        }

        return $packages;
    }

    public function getNamespaces(): array
    {
        return $this->getComposerConfig()->getPSRNamespaces();
    }
}
