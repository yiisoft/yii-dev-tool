<?php

namespace Yiisoft\YiiDevTool\Component\Travis;

use InvalidArgumentException;
use RuntimeException;

class TravisKeyPuller
{
    /** @var string */
    private $repositoryName;

    /** @var null|array */
    private $keyData;

    public function __construct(string $repositoryName)
    {
        if (!preg_match('|^[a-z0-9-]+/[a-z0-9_.-]+$|i', $repositoryName)) {
            throw new InvalidArgumentException("Invalid repository name: $repositoryName");
        }

        $this->repositoryName = $repositoryName;
    }

    public function pullPublicKey(): string
    {
        $this->ensureKeyDataPulled();

        if (!array_key_exists('key', $this->keyData)) {
            throw new RuntimeException('Key data does not contain "key" field.');
        }

        return $this->keyData['key'];
    }

    private function ensureKeyDataPulled(): void
    {
        if ($this->keyData !== null) {
            return;
        }

        $url = "https://api.travis-ci.org/repos/{$this->repositoryName}/key";

        $json = file_get_contents($url);
        if ($json === false) {
            throw new RuntimeException("Failed to get json from $url");
        }

        $keyData = json_decode($json, true);
        if ($keyData === null) {
            throw new RuntimeException("Failed to decode json.");
        }

        $this->keyData = $keyData;
    }
}
