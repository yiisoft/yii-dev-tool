<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Component\Package;

use Symfony\Component\Process\ExecutableFinder;
use Symplify\GitWrapper\GitWorkingCopy;
use Symplify\GitWrapper\GitWrapper;
use InvalidArgumentException;
use RuntimeException;

class Package
{
    private static ?GitWrapper $gitWrapper = null;
    private string $id;
    private ?string $configuredRepositoryUrl;
    private string $path;
    private ?GitWorkingCopy $gitWorkingCopy = null;
    private string $owner;

    private static function getGitWrapper(): GitWrapper
    {
        if (static::$gitWrapper === null) {
            $finder = new ExecutableFinder();
            $gitBinary = $finder->find('git');
            static::$gitWrapper = new GitWrapper($gitBinary);
        }

        return static::$gitWrapper;
    }

    public function __construct(string $id, $config, string $owner, string $packagesRootDir)
    {
        if (!preg_match('|^[a-z0-9_.-]+$|i', $id)) {
            throw new InvalidArgumentException('Package ID can contain only symbols [a-z0-9_.-].');
        }

        $this->id = $id;
        $this->owner = $owner;

        if (!is_bool($config) && !is_string($config)) {
            throw new InvalidArgumentException('Package config must contain a boolean or a string.');
        }

        if ($config === false) {
            $this->configuredRepositoryUrl = null;
        } elseif ($config === true) {
            $this->configuredRepositoryUrl = "git@github.com:$this->owner/$id.git";
        } elseif ($config === 'https') {
            $this->configuredRepositoryUrl = "https://github.com/$this->owner/$id.git";
        } elseif (preg_match('|^[a-z0-9-]+/[a-z0-9_.-]+$|i', $config)) {
            $this->configuredRepositoryUrl = "git@github.com:$config.git";
        } else {
            $this->configuredRepositoryUrl = $config;
        }

        $this->path = rtrim($packagesRootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $id;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return "{$this->getVendor()}/{$this->getId()}";
    }

    public function getVendor(): string
    {
        return $this->owner;
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
        return "https://github.com/{$this->owner}/{$this->id}.git";
    }

    public function getPossibleOriginalRepositoryUrls(): array
    {
        return [
            "https://github.com/{$this->owner}/{$this->id}.git",
            "git@github.com:{$this->owner}/{$this->id}.git",
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

    public function getComposerConfigPath(): string
    {
        return "{$this->path}/composer.json";
    }

    public function composerConfigFileExists(): bool
    {
        return file_exists($this->getComposerConfigPath());
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
}
