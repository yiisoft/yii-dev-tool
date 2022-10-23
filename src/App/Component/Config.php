<?php

namespace Yiisoft\YiiDevTool\App\Component;

use RuntimeException;

class Config
{
    private array $config;
    private string $appRootDir;

    public function __construct(string $applicationRootDir, string $configFile)
    {
        $this->appRootDir = $applicationRootDir;
        if (!file_exists($this->appRootDir) && !is_dir($this->appRootDir)) {
            throw new RuntimeException(
                'The root directory does not exist.',
            );
        }
        if (!file_exists($this->appRootDir . $configFile)) {
            throw new RuntimeException(
                'DevTool tool settings file does not exist.',
            );
        }
        $config = require $this->appRootDir . $configFile;

        if (!is_array($config)) {
            throw new RuntimeException(
                'Configuration data must be an array.',
            );
        }
        $this->config = $config;
        $this->validateData();
    }

    private function validateData(): void
    {
        $configError = '';
        if (
            !isset($this->config['owner-packages'])
            || !preg_match(
                '/^[a-z0-9][a-z0-9-]*[a-z0-9]$/i',
                $this->config['owner-packages']
            )
        ) {
            $configError .=
                "Error: Not config owner packages or packages owner name wrong.\n
                 The packages owner can only contain the characters [a-z0-9-],
                 \n and the character \'-\' cannot appear at the beginning or at the end." . '\n';
        }

        if (
            !isset($this->config['git-repository'])
            || !preg_match(
                '#^(?!-)(?:(?:[a-zA-Z\d][a-zA-Z\d\-]{0,61})?[a-zA-Z\d]\.){1,126}(?!\d+)[a-zA-Z\d]{1,63}$#',
                $this->config['git-repository']
            )
        ) {
            $configError .= 'Invalid git repository domain name.' . '\n';
        }

        if (!isset($this->config['api-token']) || !is_string($this->config['api-token'])){
            $configError .= 'No specified api git repository token. You can create a github.com token here: https://github.com/settings/tokens for other repositories, read the docs. Choose \'repo\' rights.' . '\n';
        }

        $configDir = 'config-dir';
        if (!isset($this->config[$configDir])) {
            $configError .= 'The directory name for work files is not specified.';
        } else if (!file_exists($this->getConfigDir()) && !is_dir($this->getConfigDir())) {
            $configError .= 'The configuration directory with working files does not exist.';
        }

        $packagesDir = 'packages-dir';
        if (!isset($this->config[$packagesDir])) {
            $configError .= 'There is no configuration for the packages directory.';
        } else if (!file_exists($this->getPackagesRootDir()) && !is_dir($this->getPackagesRootDir())) {
            $configError .= 'The packages directory does not exist.';
        }

        $packagesError = $this->validatePackages();
        $configError .= $packagesError;

        if (!empty($configError)) {
            throw new RuntimeException($configError);
        }
    }

    private function validatePackages(): string
    {
        $packagesErrors = '';
        if (!isset($this->config['packages']) || !is_array($this->config['packages'])) {
            throw new RuntimeException('No settings specified for packages in config or this is not an array with packages settings.');
        }
        foreach ($this->config['packages'] as $packageName => $packageConfig) {
            if (!preg_match('|^[a-z0-9_.-]+$|i', $packageName)) {
                $packagesErrors .= "$packageName Package ID can contain only symbols [a-z0-9_.-].\n";
            }

            if (
                is_bool($packageConfig)
                || $packageConfig === 'https'
                || preg_match('#^[a-z0-9][a-z0-9-]*[a-z0-9]/[a-z0-9_.-]+$#i', $packageConfig)
                || preg_match('#^(git@|https://)(github.com|gitlab.com|bitbucket.org)([:/])([a-z0-9-]+)/([a-z0-9_.-]+)(.git)$#i', $packageConfig)
            ) {
                continue;
            }

            $packagesErrors .= "Package $packageName config must contain a boolean or a variant of the string `https`, `ownerName/repositoryName` or a link to the repository.\n";
        }

        return $packagesErrors;
    }

    public function get($configName): mixed
    {
        if (!isset($this->config[$configName])) {
            throw new RuntimeException("There is no given `$configName` setting in the configuration.");
        }
        return $this->config[$configName];
    }

    public function getConfigDir(): string
    {
        return $this->appRootDir . $this->config['config-dir'];
    }

    public function getPackagesRootDir(): string
    {
        return $this->appRootDir . $this->config['packages-dir'];
    }

    public function getOwner(): string
    {
        return $this->config['owner-packages'];
    }

    public function getGitRepository(): string
    {
        return $this->config['git-repository'];
    }

    public function getApiToken(): string
    {
        return $this->config['api-token'];
    }

    public function getPackages(): array
    {
        return $this->config['packages'];
    }

}
