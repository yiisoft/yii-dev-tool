<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Infrastructure\Composer\Config;

use InvalidArgumentException;
use RuntimeException;
use Yiisoft\YiiDevTool\Infrastructure\Composer\Config\Dependency\ComposerConfigDependencyList;
use function array_key_exists;

class ComposerConfig
{
    public const SECTION_MINIMUM_STABILITY = 'minimum-stability';
    public const SECTION_PROVIDE = 'provide';
    public const SECTION_REQUIRE = 'require';
    public const SECTION_REQUIRE_DEV = 'require-dev';

    private function __construct(private array $data)
    {
    }

    public static function getAllDependencySections(): array
    {
        return [
            self::SECTION_REQUIRE,
            self::SECTION_REQUIRE_DEV,
        ];
    }

    public static function validateDependencySection(string $section): void
    {
        if (!in_array($section, self::getAllDependencySections(), true)) {
            throw new InvalidArgumentException('Invalid section.');
        }
    }

    public static function createByArray(array $config): self
    {
        return new self($config);
    }

    public static function createByJson(string $json): self
    {
        $config = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Failed to decode JSON.');
        }

        return static::createByArray($config);
    }

    public static function createByFilePath(string $path): self
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException('Failed to read file ' . $path);
        }

        return static::createByJson($content);
    }

    public function asArray(): array
    {
        return $this->data;
    }

    public function asPrettyJson(): string
    {
        $content = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($content === false) {
            throw new RuntimeException('Failed to encode JSON.');
        }

        return $content;
    }

    public function writeToFile(string $targetPath): self
    {
        $result = file_put_contents($targetPath, $this->asPrettyJson() . "\n");

        if ($result === false) {
            throw new RuntimeException('Failed to write file ' . $targetPath);
        }

        return $this;
    }

    public function getPSRNamespaces(): array
    {
        $namespaces = [];

        if (isset($this->data['autoload']['psr-4'])) {
            $namespaces = array_merge($namespaces, array_keys($this->data['autoload']['psr-4']));
        }

        if (isset($this->data['autoload-dev']['psr-4'])) {
            $namespaces = [...$namespaces, ...array_keys($this->data['autoload-dev']['psr-4'])];
        }

        if (isset($this->data['autoload']['psr-0'])) {
            $namespaces = [...$namespaces, ...array_keys($this->data['autoload']['psr-0'])];
        }

        if (isset($this->data['autoload-dev']['psr-0'])) {
            $namespaces = [...$namespaces, ...array_keys($this->data['autoload-dev']['psr-0'])];
        }

        return $namespaces;
    }

    public function usesNonPSRAutoload(): bool
    {
        return isset($this->data['autoload']['classmap'])
            || isset($this->data['autoload']['files'])
            || isset($this->data['autoload-dev']['classmap'])
            || isset($this->data['autoload-dev']['files']);
    }

    public function sortPackagesEnabled(): bool
    {
        return isset($this->data['config']['sort-packages']) && $this->data['config']['sort-packages'] === true;
    }

    public function hasSection(string $section): bool
    {
        return array_key_exists($section, $this->data);
    }

    public function getSection(string $section)
    {
        return $this->hasSection($section) ? $this->data[$section] : null;
    }

    public function setSection(string $section, $data): void
    {
        $this->data[$section] = $data;
    }

    public function removeSection($section): void
    {
        if ($this->hasSection($section)) {
            unset($this->data[$section]);
        }
    }

    public function getDependencyList(string $section): ComposerConfigDependencyList
    {
        self::validateDependencySection($section);

        return new ComposerConfigDependencyList($this->getSection($section));
    }

    public function setDependencyList(string $section, ComposerConfigDependencyList $dependencyList): self
    {
        self::validateDependencySection($section);

        $this->setSection($section, $dependencyList->asArray());

        return $this;
    }
}
