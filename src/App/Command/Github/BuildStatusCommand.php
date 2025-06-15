<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Github;

use Github\Api\Repository\Checks\CheckRuns;
use Github\Client;
use RuntimeException;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;

final class BuildStatusCommand extends PackageCommand
{
    protected function configure()
    {
        $this
            ->setName('github/build-status')
            ->setDescription('Check build status of current commit');

        parent::configure();
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<success>✔ OK</success>';
    }

    protected function processPackage(Package $package): void
    {
        $currentCommit = trim($package
        ->getGitWorkingCopy()
        ->run('rev-parse', argsOrOptions: ['HEAD']));

        $io = $this->getIO();
        $io->preparePackageHeader($package, 'Gettting status for {package} commit ' . $currentCommit);



        $client = new Client();
        $client->authenticate($this->getToken(), null, Client::AUTH_ACCESS_TOKEN);

        $checksRunsApi = new CheckRuns($client);

        $checks = $checksRunsApi->allForReference(
            username: $package->getVendor(),
            repository: $package->getId(),
            ref: $currentCommit
        );

        foreach ($checks['check_runs'] as $check) {
            if ($check['status'] === 'completed') {
                if ($check['conclusion'] === 'success') {
                    $io->important()->success("✔ <href={$check['html_url']}>{$check['name']}</>");
                } else {
                    $io->important()->error("× <href={$check['html_url']}>{$check['name']}</>");
                }
            } else {
                $io->important()->info("» <href={$check['html_url']}>{$check['name']}</>: {$check['status']}\n");
            }
        }
        $io->done();
    }

    private function getSettings(): array
    {
        /** @noinspection PhpIncludeInspection */
        return require $this->getAppRootDir() . 'config/settings.php';
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
