<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Github;

use Github\Api\Repo;
use Github\AuthMethod;
use Github\Client;
use Github\Exception\RuntimeException as GithubRuntimeException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;

#[AsCommand(
    name: 'github/sync',
    description: 'Sync forks from upstream repositories'
)]
final class SyncUpstreamRepositoriesCommand extends PackageCommand
{
    private InputInterface $input;

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        parent::initialize($input, $output);
    }

    protected function configure()
    {
        $this
            ->setAliases(['sync'])
            ->addOption(
                'branch',
                'b',
                InputOption::VALUE_REQUIRED,
                'Name of the branch to be synchronized',
                'master'
            );

        parent::configure();
    }

    protected function processPackage(Package $package): void
    {
        $repository = $this->getGithubApiRepository();
        $branchName = $this->input->getOption('branch');

        try {
            preg_match('|^[a-z0-9-]+/([a-z0-9_.-]+)$|i', $package->getName(), $repoMatches);
            if (isset($repoMatches[1])) {
                $repository->mergeUpstream($package->getVendor(), $repoMatches[1], $branchName);
                $this->getIO()->important()->success("Repository successfully synced: {$package->getName()}");
            } else {
                $this->getIO()->error(
                    $this->errorMessage($package->getName())
                );
            }
        } catch (GithubRuntimeException $e) {
            $this->getIO()->error(
                $this->errorMessage($package->getName(), $e->getMessage())
            );
        }
    }

    private function errorMessage($packageName, $message = ''): array
    {
        $error = $message ? "{$packageName}: {$message}" : $packageName;
        return [
            "Error when syncing a repository $error ",
            'Check if the nickname of the owner and the name of the repository are correct',
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
