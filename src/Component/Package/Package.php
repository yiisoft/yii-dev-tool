<?php

namespace Yiisoft\YiiDevTool\Component\Package;

use GitWrapper\GitWorkingCopy;
use GitWrapper\GitWrapper;
use InvalidArgumentException;
use RuntimeException;

class Package
{
    private static ?GitWrapper $gitWrapper = null;
    private string $id;
    private ?string $configuredRepositoryUrl;
    private string $path;
    private ?string $error = null;
    private ?string $errorDuring;
    private ?GitWorkingCopy $gitWorkingCopy = null;

    private static function getGitWrapper(): GitWrapper
    {
        if (static::$gitWrapper === null) {
            static::$gitWrapper = new GitWrapper();
        }

        return static::$gitWrapper;
    }

    public function __construct(string $id, $config)
    {
        if (!preg_match('|^[a-z0-9_.-]+$|i', $id)) {
            throw new InvalidArgumentException('Package ID can contain only symbols [a-z0-9_.-].');
        }

        $this->id = $id;

        if (!is_bool($config) && !is_string($config)) {
            throw new InvalidArgumentException('Package config must contain a boolean or a string.');
        }

        if ($config === false) {
            $this->configuredRepositoryUrl = null;
        } elseif ($config === true) {
            $this->configuredRepositoryUrl = "git@github.com:yiisoft/$id.git";
        } elseif ($config === 'https') {
            $this->configuredRepositoryUrl = "https://github.com/yiisoft/$id.git";
        } elseif (preg_match('|^[a-z0-9-]+/[a-z0-9_.-]+$|i', $config)) {
            $this->configuredRepositoryUrl = "git@github.com:$config.git";
        } else {
            $this->configuredRepositoryUrl = $config;
        }

        $this->path = realpath(__DIR__ . '/../../../') . '/dev/' . $id;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getConfiguredRepositoryUrl(): string
    {
        if ($this->configuredRepositoryUrl === null) {
            throw new RuntimeException('Package does not have repository url.');
        }

        return $this->configuredRepositoryUrl;
    }

    public function getOriginalRepositoryHttpsUrl(): string
    {
        return "https://github.com/yiisoft/{$this->id}.git";
    }

    public function getPossibleOriginalRepositoryUrls(): array
    {
        return [
            "https://github.com/yiisoft/{$this->id}.git",
            "git@github.com:yiisoft/{$this->id}.git",
        ];
    }

    public function isConfiguredRepositoryPersonal(): bool
    {
        return !in_array(
            $this->getConfiguredRepositoryUrl(),
            $this->getPossibleOriginalRepositoryUrls(),
            true
        );
    }

    public function disabled(): bool
    {
        return $this->configuredRepositoryUrl === null;
    }

    public function enabled(): bool
    {
        return !$this->disabled();
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function doesPackageDirectoryExist(): bool
    {
        return file_exists($this->path);
    }

    public function isGitRepositoryCloned(): bool
    {
        return file_exists("{$this->path}/.git");
    }

    // TODO: Call all git commands through this interface
    public function getGitWorkingCopy(): GitWorkingCopy
    {
        if (!$this->isGitRepositoryCloned()) {
            throw new RuntimeException('Package does not have git working copy.');
        }

        if ($this->gitWorkingCopy === null) {
            $this->gitWorkingCopy = static::getGitWrapper()->workingCopy($this->path);
        }

        return $this->gitWorkingCopy;
    }

    public function setError(string $error, string $during): void
    {
        $this->error = $error;
        $this->errorDuring = $during;
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getErrorDuring(): ?string
    {
        return $this->errorDuring;
    }
}
