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
        $settingsFile = $this->getConfig()->getConfigDir() . 'settings.php';
        if (!file_exists($settingsFile)) {
            throw new RuntimeException("There's no file settings.php in config directory");
        }
        return require $settingsFile;
    }

    private function getToken(): string
    {
        return $this->getConfig()->getApiToken();
    }
}
