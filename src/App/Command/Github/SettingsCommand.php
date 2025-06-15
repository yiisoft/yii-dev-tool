<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Github;

use Github\Api\Repo;
use Github\AuthMethod;
use Github\Client;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\App\Component\GitHubTokenAware;

final class SettingsCommand extends PackageCommand
{
    use GitHubTokenAware;

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
        $client->authenticate($this->getGitHubToken(), null, AuthMethod::ACCESS_TOKEN);
        $repoApi = new Repo($client);

        $repoApi->update($package->getVendor(), $package->getId(), $this->getSettings());
    }

    private function getSettings(): array
    {
        /** @noinspection PhpIncludeInspection */
        return require $this->getAppRootDir() . 'config/settings.php';
    }
}
