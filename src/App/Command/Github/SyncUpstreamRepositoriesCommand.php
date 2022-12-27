<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Github;

use Github\Api\Repo;
use Github\AuthMethod;
use Github\Client;
use Github\Exception\RuntimeException as GithubRuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;

final class SyncUpstreamRepositoriesCommand extends PackageCommand
{
    protected static $defaultName = 'github/sync';
    protected static $defaultDescription = 'Sync forks from upstream repositories';

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
            $repository->mergeUpstream($package->getVendor(), $package->getId(), $branchName);
            $this->getIO()->important()->success("Repository successfully synced: {$package->getName()}");
        } catch (GithubRuntimeException $e) {
            $this->getIO()->error(
                $this->errorMessage($package->getName(), $e->getMessage())
            );
        }
    }

    private function getGithubApiRepository(): Repo
    {
        $client = new Client();
        $client->authenticate($this->getToken(), null, AuthMethod::ACCESS_TOKEN);
        return new Repo($client);
    }

    private function getToken(): string
    {
        return $this->getConfig()->getApiToken();
    }

    private function errorMessage($packageName, $message = ''): array
    {
        $error = $message ? "{$packageName}: {$message}" : $packageName;
        return [
            "Error when syncing a repository $error ",
            'Check if the nickname of the owner and the name of the repository are correct',
        ];
    }
}
