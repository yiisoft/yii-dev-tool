<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Github;

use Github\Api\Repo;
use Github\Client;
use RuntimeException;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;

final class SettingsCommand extends PackageCommand
{
    protected function configure()
    {
        $this
            ->setName('github/settings')
            ->setDescription('Apply settings to GitHub repositories');

        parent::configure();
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<success>✔ Done</success>';
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, 'Adjusting settings for {package}');

        $client = new Client();
        $client->authenticate($this->getToken(), null, Client::AUTH_ACCESS_TOKEN);
        $repoApi = new Repo($client);

        $repoApi->update($package->getVendor(), $package->getId(), $this->getSettings());
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
