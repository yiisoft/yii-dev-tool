<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Component\Composer;

use RuntimeException;

class ComposerConfig
{
    public const SECTION_REQUIRE = 'require';
    public const SECTION_REQUIRE_DEV = 'require-dev';

    private array $data;

    private function __construct(array $data)
    {
        $this->data = $data;
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

    /**
     * TODO: Move merge logic to a separate class
     * @param ComposerConfig $anotherComposerConfig
     * @return $this
     */
    public function merge(ComposerConfig $anotherComposerConfig): self
    {
        $this->data = $this->internalMerge($this->data, $anotherComposerConfig->asArray());

        return $this;
    }

    public function writeToFile(string $targetPath): self
    {
        $result = file_put_contents($targetPath, $this->asPrettyJson() . "\n");

        if ($result === false) {
            throw new RuntimeException('Failed to write file ' . $targetPath);
        }

        return $this;
    }

    public function getDependencies(?string $specificVendor = null, $skipVirtual = false): array
    {
        $config = $this->asArray();

        $dependencies = [];

        if (array_key_exists('require', $config)) {
            $dependencies = array_merge($dependencies, $config['require']);
        }

        if (array_key_exists('require-dev', $config)) {
            $dependencies = array_merge($dependencies, $config['require-dev']);
        }

        if ($specificVendor !== null) {
            $dependencies = array_filter(
                $dependencies,
                fn($key) => strpos($key, "$specificVendor/") === 0,
                ARRAY_FILTER_USE_KEY
            );
        } elseif ($skipVirtual === true) {
            $dependencies = array_filter(
                $dependencies,
                fn($key) => strpos($key, '/') !== false,
                ARRAY_FILTER_USE_KEY
            );
        }

        return $dependencies;
    }

    public function getPSRNamespaces(): array
    {
        $namespaces = [];

        if (isset($this->data["autoload"]["psr-4"])) {
            $namespaces = array_merge($namespaces, array_keys($this->data["autoload"]["psr-4"]));
        }

        if (isset($this->data["autoload-dev"]["psr-4"])) {
            $namespaces = array_merge($namespaces, array_keys($this->data["autoload-dev"]["psr-4"]));
        }

        if (isset($this->data["autoload"]["psr-0"])) {
            $namespaces = array_merge($namespaces, array_keys($this->data["autoload"]["psr-0"]));
        }

        if (isset($this->data["autoload-dev"]["psr-0"])) {
            $namespaces = array_merge($namespaces, array_keys($this->data["autoload-dev"]["psr-0"]));
        }

        return $namespaces;
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

    private function internalMerge(array $a, array $b): array
    {
        foreach ($b as $key => $value) {
            if (is_string($key)) {
                if (array_key_exists($key, $a) && is_array($value)) {
                    $a[$key] = $this->internalMerge($a[$key], $value);
                } else {
                    $a[$key] = $value;
                }
            } else {
                $index = array_search($value, $a, true);

                if ($index === false) {
                    $a[] = $value;
                } else {
                    $a[$index] = $value;
                }
            }
        }

        return $a;
    }
}
