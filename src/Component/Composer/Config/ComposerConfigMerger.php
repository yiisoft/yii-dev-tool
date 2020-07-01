<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Component\Composer\Config;

class ComposerConfigMerger
{
    public function merge(ComposerConfig $firstConfig, ComposerConfig $secondConfig): ComposerConfig
    {
        $resultData = $this->internalMerge($firstConfig->asArray(), $secondConfig->asArray());

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
}
