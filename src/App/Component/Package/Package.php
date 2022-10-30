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
    private ?string $configuredRepositoryUrl = null;
    private string $path;
    private ?GitWorkingCopy $gitWorkingCopy = null;

    private static function getGitWrapper(): GitWrapper
    {
        if (static::$gitWrapper === null) {
            $finder = new ExecutableFinder();
            $gitBinary = $finder->find('git');
            static::$gitWrapper = new GitWrapper($gitBinary);
        }

        return static::$gitWrapper;
    }

    public function __construct(private string $id, bool|string $config, private string $owner, string $packagesRootDir, string $gitRepository)
    {
        if ($config === false) {
            $this->configuredRepositoryUrl = null;
        } elseif ($config === true) {
            $this->configuredRepositoryUrl = "git@$gitRepository:$this->owner/$id.git";
        } elseif ($config === 'https') {
            $this->configuredRepositoryUrl = "https://$gitRepository/$this->owner/$id.git";
        } elseif (preg_match('|^[a-z0-9-]+/[a-z0-9_.-]+$|i', $config)) {
            preg_match('|^([a-z0-9-]+)/[a-z0-9_.-]+$|i', $config, $ownerMatches);
            $this->owner = $ownerMatches[1] ?? $owner;
            $this->configuredRepositoryUrl = "git@$gitRepository:$config.git";
        } else {
            preg_match('#^(git@|https://)(github.com|gitlab.com|bitbucket.org)([:/])([a-z0-9-]+)/([a-z0-9_.-]+)(.git)$#i', $config, $ownerMatches);
            $this->owner = $ownerMatches[4] ?? $owner;
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
