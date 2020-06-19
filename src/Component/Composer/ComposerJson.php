<?php

namespace Yiisoft\YiiDevTool\Component\Composer;

use RuntimeException;

class ComposerJson
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public static function createByContent(string $content): self
    {
        $config = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Failed to decode JSON.');
        }

        return new self($config);
    }

    public static function createByPath(string $path): self
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException('Failed to read file ' . $path);
        }

        return static::createByContent($content);
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getContent(): string
    {
        $content = json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($content === false) {
            throw new RuntimeException('Failed to encode JSON.');
        }

        return $content;
    }

    public function merge(ComposerJson $anotherComposerJson): self
    {
        $this->config = $this->internalMerge($this->config, $anotherComposerJson->getConfig());

        return $this;
    }

    public function writeToFile(string $targetPath): self
    {
        $result = file_put_contents($targetPath, $this->getContent());

        if ($result === false) {
            throw new RuntimeException('Failed to write file ' . $targetPath);
        }

        return $this;
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
