<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Component\Package;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symplify\GitWrapper\GitWorkingCopy;
use Symplify\GitWrapper\GitWrapper;

class Package
{
    private static ?GitWrapper $gitWrapper = null;

    private string $id;
    private ?string $configuredRepositoryUrl = null;
    private string $path;
    private ?GitWorkingCopy $gitWorkingCopy = null;
    private bool $enabled = true;
    private bool $isMonoRepository = false;

    public function __construct(string $id, $config, private string $owner, string $packagesRootDir, private ?self $rootPackage)
    {
        if (!preg_match('|^[a-z0-9_.-]+$|i', $id)) {
            throw new InvalidArgumentException('Package ID can contain only symbols [a-z0-9_.-].');
        }

        $this->id = $id;

        if (!is_bool($config) && !is_string($config) && !is_array($config)) {
            throw new InvalidArgumentException('Package config must be either a boolean, a string or an array.');
        }

        $enabled = false;
        $isMonoRepository = false;
        $repositoryUrl = null;
        if (is_array($config)) {
            if (($config['enabled'] ?? false) === true) {
                $enabled = true;
            }
            if (($config['monorepo'] ?? false) === true) {
                $isMonoRepository = true;
            }
        } elseif (is_bool($config)) {
            $enabled = $config;
        } elseif (is_string($config)) {
            $enabled = true;
            if ($config === 'https') {
                $repositoryUrl = "https://github.com/$this->owner/$id.git";
            } elseif (preg_match('|^[a-z0-9-]+/[a-z0-9_.-]+$|i', $config)) {
                preg_match('|^([a-z0-9-]+)/[a-z0-9_.-]+$|i', $config, $ownerMatches);
                $this->owner = $ownerMatches[1] ?? $owner;
                $repositoryUrl = "git@github.com:$config.git";
            } else {
                preg_match('|^git@github.com:([a-z0-9-]+)/[a-z0-9_.-]+$|i', $config, $ownerMatches);
                $this->owner = $ownerMatches[1] ?? $owner;
                $repositoryUrl = $config;
            }
        }
        if ($enabled && $repositoryUrl === null) {
            $repositoryUrl = "git@github.com:$this->owner/$id.git";
        }

        $this->configuredRepositoryUrl = !$enabled ? null : $repositoryUrl;

        $this->enabled = $enabled;
        $this->isMonoRepository = $isMonoRepository;
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
        if ($this->isVirtual()) {
            return $this->rootPackage->getConfiguredRepositoryUrl();
        }

        if ($this->configuredRepositoryUrl === null) {
            throw new RuntimeException('Package does not have repository url.');
        }

        return $this->configuredRepositoryUrl;
    }

    public function disabled(): bool
    {
        return !$this->enabled;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function getPath(): string
    {
        if ($this->isVirtual()) {
            return $this->rootPackage->getPath() . DIRECTORY_SEPARATOR . $this->id;
        }

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

    public function isGitRepositoryCloned(): bool
    {
        if ($this->isVirtual()) {
            return $this->rootPackage->isGitRepositoryCloned();
        }

        return file_exists("{$this->path}/.git");
    }

    public function isVirtual(): bool
    {
        return $this->rootPackage !== null;
    }

    private static function getGitWrapper(): GitWrapper
    {
        if (static::$gitWrapper === null) {
            $finder = new ExecutableFinder();
            $gitBinary = $finder->find('git');
            static::$gitWrapper = new GitWrapper($gitBinary);
        }

        return static::$gitWrapper;
    }

    // TODO: Call all git commands through this interface
    public function getGitWorkingCopy(): GitWorkingCopy
    {
        if (!$this->isGitRepositoryCloned()) {
            throw new RuntimeException('Package does not have git working copy.');
        }

        if ($this->gitWorkingCopy === null) {
            $path = $this->path;
            if ($this->isVirtual()) {
                $path = $this->rootPackage->getPath();
            }
            $this->gitWorkingCopy = static::getGitWrapper()->workingCopy($path);
        }

        return $this->gitWorkingCopy;
    }

    public function setEnabled(bool $value): void
    {
        $this->enabled = $value;
    }

    public function getRootPackage(): ?self
    {
        return $this->rootPackage;
    }

    public function isMonoRepository(): bool
    {
        return $this->isMonoRepository;
    }
}
