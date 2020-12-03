<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Infrastructure\Composer;

use RuntimeException;
use Yiisoft\YiiDevTool\Infrastructure\Composer\Config\ComposerConfig;

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

    public function getComposerConfigPath(): string
    {
        return "{$this->path}/composer.json";
    }

    public function composerConfigFileExists(): bool
    {
        return file_exists($this->getComposerConfigPath());
    }

    public function getComposerConfig(): ComposerConfig
    {
        if (!$this->composerConfigFileExists()) {
            throw new RuntimeException('Failed to get ComposerConfig because composer.json does not exist.');
        }

        if ($this->config === null) {
            $this->config = ComposerConfig::createByFilePath($this->getComposerConfigPath());
        }

        return $this->config;
    }

    public function getPSRNamespaces(): array
    {
        return $this->getComposerConfig()->getPSRNamespaces();
    }

    public function usesNonPSRAutoload(): bool
    {
        return $this->getComposerConfig()->usesNonPSRAutoload();
    }

    public function providesBinaries(): bool
    {
        return $this->getComposerConfig()->binSectionDefined();
    }

    public function getProvidedPackagesAsArray(): array
    {
        $provideSectionData = $this->getComposerConfig()->getSection(ComposerConfig::SECTION_PROVIDE);

        return $provideSectionData ?? [];
    }

    public function doesProvidePackage(string $packageName): bool
    {
        return array_key_exists($packageName, $this->getProvidedPackagesAsArray());
    }
}
