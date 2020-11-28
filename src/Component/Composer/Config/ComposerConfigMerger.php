<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Component\Composer\Config;

class ComposerConfigMerger
{
    public function merge(ComposerConfig $firstConfig, ComposerConfig $secondConfig): ComposerConfig
    {
        $resultData = $this->internalMerge($firstConfig->asArray(), $secondConfig->asArray());
        $resultData = $this->sortDependencies($resultData);

        return ComposerConfig::createByArray($resultData);
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

    private function sortDependencies(array $config): array
    {
        if (!(bool) ($config['config']['sort-packages'] ?? false)) {
            return $config;
        }

        if (array_key_exists('require', $config)) {
            $config['require'] = $this->sortInternal($config['require']);
        }
        if (array_key_exists('require-dev', $config)) {
            $config['require-dev'] = $this->sortInternal($config['require-dev']);
        }

        return $config;
    }

    private function sortInternal(array $packages): array
    {
        uksort($packages, 'strnatcmp');

        $extensions = [];
        foreach ($packages as $package => $version) {
            if (preg_match('/^ext-[a-z-]+$/', $package)) {
                unset($packages[$package]);
                $extensions[$package] = $version;
            }
        }
        $packages = array_merge($extensions, $packages);

        if (array_key_exists('php', $packages)) {
            $php = $packages['php'];
            unset($packages['php']);
            $packages = array_merge(['php' => $php], $packages);
        }

        return $packages;
    }
}
