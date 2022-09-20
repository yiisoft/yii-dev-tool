<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Github;

use Github\Api\Repo;
use Github\AuthMethod;
use Github\Client;
use Github\Exception\RuntimeException as GithubRuntimeException;
use RuntimeException;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;

final class SyncUpstreamRepositoriesCommand extends PackageCommand
{
    protected static $defaultName = 'github/sync';
    protected static $defaultDescription = 'Sync forks from upstream repositories';

    protected function configure()
    {
        $this
            ->setAliases(['sync']);

        parent::configure();
    }

    protected function processPackage(Package $package): void
    {
        $repository = $this->getGithubApiRepository();

        try {
            preg_match('|^[a-z0-9-]+/([a-z0-9_.-]+)$|i', $package->getName(), $repoMatches);
            if (isset($repoMatches[1])) {
                $repository->mergeUpstream($package->getVendor(), $repoMatches[1]);
                $this->getIO()->write("<success>Repository successfully synced: {$package->getName()}</success>" . PHP_EOL);
            } else {
                $this->getIO()->error(
                    $this->errorMessage($package->getName())
                );
            }
        } catch (GithubRuntimeException $e) {
            $this->getIO()->write(
                $this->errorMessage($package->getName(), $e->getMessage())
            );
        }
    }

    private function errorMessage($packageName, $message = ''): array
    {
        $error = $message ? "{$packageName}: {$message}" : $packageName;
        return [
            "<error>Error when syncing a repository $error </error>",
            '<error>Check if the nickname of the owner and the name of the repository are correct</error>' . PHP_EOL,
        ];
    }

    private function getGithubApiRepository(): Repo
    {
        $client = new Client();
        $client->authenticate($this->getToken(), null, AuthMethod::ACCESS_TOKEN);
        // Remove anonymous class when method is added to github-api package
        // https://github.com/KnpLabs/php-github-api/issues/1083
        return new class ($client) extends Repo {
            public function mergeUpstream($username, $repository, $branchName = null)
            {
                return $this->post(
                    '/repos/' . rawurlencode($username) . '/' . rawurlencode($repository) . '/merge-upstream',
                    ['branch' => $branchName ?? 'main']
                );
            }
        };
        // return (new Repo($client));
    }

    private function getToken(): string
    {
        $tokenFile = $this->getAppRootDir() . 'config/github.token';
        if (!file_exists($tokenFile)) {
            throw new RuntimeException("There's no $tokenFile. Please create one and put your GitHub token there. You may create it here: https://github.com/settings/tokens. Choose 'repo' rights.");
        }

        return trim(file_get_contents($tokenFile));
    }
}
