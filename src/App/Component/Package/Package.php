<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Component\Package;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symplify\GitWrapper\GitWorkingCopy;
use Symplify\GitWrapper\GitWrapper;

class Package
{
    private static ?GitWrapper $gitWrapper = null;
    private ?string $configuredRepositoryUrl = null;
    private string $path;
    private ?GitWorkingCopy $gitWorkingCopy = null;
    private bool $enabled = true;
    private bool $isMonoRepository = false;

    public function __construct(
        private string $id,
        $config,
        private string $owner,
        string $packagesRootDir,
        string $gitRepository,
        private ?self $rootPackage
    ) {
        $enabled = false;
        $isMonoRepository = false;
        $repositoryUrl = null;
        if (is_array($config)) {
            $enabled = (bool)($config['enabled'] ?? false);
            $isMonoRepository = (bool)($config['monorepo'] ?? false);
            if ($enabled === true) {
                $this->configuredRepositoryUrl = "git@$gitRepository:$this->owner/$id.git";
            }
        } elseif (is_bool($config)) {
            $enabled = $config;
            if ($enabled === true) {
                $this->configuredRepositoryUrl = "git@$gitRepository:$this->owner/$id.git";
            }
        } elseif ($config === 'https') {
            $this->configuredRepositoryUrl = "https://$gitRepository/$this->owner/$id.git";
        } elseif (preg_match('|^[a-z0-9-]+/[a-z0-9_.-]+$|i', $config)) {
            preg_match('|^([a-z0-9-]+)/[a-z0-9_.-]+$|i', $config, $ownerMatches);
            $this->owner = $ownerMatches[1] ?? $owner;
            $this->configuredRepositoryUrl = "git@$gitRepository:$config.git";
        } else {
            preg_match(
                '#^(git@|https://)(github.com|gitlab.com|bitbucket.org)([:/])([a-z0-9-]+)/([a-z0-9_.-]+)(.git)$#i',
                $config,
                $ownerMatches
            );
            $this->owner = $ownerMatches[4] ?? $owner;
            $this->configuredRepositoryUrl = $config;
        }

        $this->enabled = $enabled;
        $this->isMonoRepository = $isMonoRepository;
        $this->path = rtrim($packagesRootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $id;
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

    public function getName(): string
    {
        return "{$this->getVendor()}/{$this->getId()}";
    }

    public function getVendor(): string
    {
        return $this->owner;
    }

    public function getId(): string
    {
        return $this->id;
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

    public function composerConfigFileExists(): bool
    {
        return file_exists($this->getComposerConfigPath());
    }

    public function getComposerConfigPath(): string
    {
        return "{$this->path}/composer.json";
    }

    public function isVirtual(): bool
    {
        return $this->rootPackage !== null;
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

    public function isGitRepositoryCloned(): bool
    {
        if ($this->isVirtual()) {
            return $this->rootPackage->isGitRepositoryCloned();
        }

        return file_exists("{$this->path}/.git");
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
