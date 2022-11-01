<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Component;

use RuntimeException;

class Config
{
    private array $config;

    public function __construct(private string $appRootDir, string $configFile)
    {
        if (!file_exists($this->appRootDir) && !is_dir($this->appRootDir)) {
            throw new RuntimeException(
                'Config Error: The root directory does not exist.',
            );
        }
        if (!file_exists($this->appRootDir . $configFile)) {
            throw new RuntimeException(
                'Config Error: DevTool tool config file does not exist.',
            );
        }
        $config = require $this->appRootDir . $configFile;

        if (!is_array($config)) {
            throw new RuntimeException(
                'Config Error: Configuration data must be an array.',
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
            $configError .= <<<ERROR
            Config error: Not config `owner-packages` or packages owner name wrong.
            The packages owner can only contain the characters [a-z0-9-], and the character '-' cannot specified at the beginning or at the end.\n
            ERROR;
        }

        if (
            !isset($this->config['git-repository'])
            || !preg_match(
                '#^(?!-)(?:(?:[a-zA-Z\d][a-zA-Z\d\-]{0,61})?[a-zA-Z\d]\.){1,126}(?!\d+)[a-zA-Z\d]{1,63}$#',
                $this->config['git-repository']
            )
        ) {
            $configError .= "Config error: An invalid git repository domain name was specified for `git-repository` config.\n";
        }

        if (!isset($this->config['api-token']) || !is_string($this->config['api-token'])) {
            $configError .= "Config error: `api-token` not specified to access git repository API. You can create a github.com token here: https://github.com/settings/tokens for other repositories, read the docs. Choose 'repo' rights.\n";
        }

        $configDir = 'config-dir';
        if (!isset($this->config[$configDir])) {
            $configError .= "Config error: The folder name for the `config-dir` config with working files was not specified.\n";
        } elseif (!file_exists($this->getConfigDir()) && !is_dir($this->getConfigDir())) {
            $configError .= "Config error: The specified folder for config `config-dir` does not exist.\n";
        }

        $packagesDir = 'packages-dir';
        if (!isset($this->config[$packagesDir])) {
            $configError .= "Config error: No folder name specified for config `packages-dir`\n";
        } elseif (!file_exists($this->getPackagesRootDir()) && !is_dir($this->getPackagesRootDir())) {
            $configError .= "Config error: The specified folder for config `packages-dir` does not exist.\n";
        }

        $packagesError = $this->validatePackages();
        $configError .= $packagesError;

        if (!empty($configError)) {
            throw new RuntimeException($configError);
        }
    }

    public function getConfigDir(): string
    {
        return $this->appRootDir . $this->config['config-dir'];
    }

    public function getPackagesRootDir(): string
    {
        return $this->appRootDir . $this->config['packages-dir'];
    }

    private function validatePackages(): string
    {
        $packagesErrors = '';
        if (!isset($this->config['packages']) || !is_array($this->config['packages'])) {
            return 'Config error: No settings specified for packages in config or this is not an array with packages settings.';
        }

        foreach ($this->config['packages'] as $packageName => $packageConfig) {
            if (!preg_match('|^[a-z0-9_.-]+$|i', $packageName)) {
                $packagesErrors .= "Config error: Package `$packageName` Package ID can contain only symbols [a-z0-9_.-].\n";
            }

            if (
                is_bool($packageConfig)
                || $packageConfig === 'https'
                || preg_match('#^[a-z0-9][a-z0-9-]*[a-z0-9]/[a-z0-9_.-]+$#i', $packageConfig)
                || preg_match(
                    '#^(git@|https://)(github.com|gitlab.com|bitbucket.org)([:/])([a-z0-9-]+)/([a-z0-9_.-]+)(.git)$#i',
                    $packageConfig
                )
            ) {
                continue;
            }

            $packagesErrors .= "Config error: Package `$packageName` config must contain a boolean or a variant of the string `https`, `ownerName/repositoryName` or a link to the repository.\n";
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
