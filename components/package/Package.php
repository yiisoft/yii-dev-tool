<?php

namespace yiidev\components\package;

use InvalidArgumentException;

class Package
{
    /** @var string */
    private $id;

    /** @var string|null */
    private $repositoryUrl;

    /** @var string */
    private $path;

    /** @var string|null */
    private $error;

    /** @var string|null */
    private $errorDuring;

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
            $this->repositoryUrl = null;
        } elseif ($config === true) {
            $this->repositoryUrl = "git@github.com:yiisoft/$id.git";
        } elseif ($config === 'https') {
            $this->repositoryUrl = "https://github.com/yiisoft/$id.git";
        } elseif (preg_match('|^[a-z0-9-]+/[a-z0-9_.-]+$|i', $config)) {
            $this->repositoryUrl = "git@github.com:$config.git";
        } else {
            $this->repositoryUrl = $config;
        }

        $this->path = realpath(__DIR__ . '/../../') . '/dev/' . $id;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getRepositoryUrl(): ?string
    {
        return $this->repositoryUrl;
    }

    public function disabled(): bool
    {
        return $this->repositoryUrl === null;
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
        return file_exists($this->getPath());
    }

    public function isGitRepositoryCloned(): bool
    {
        return file_exists("{$this->getPath()}/.git");
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
