<?php

namespace Yiisoft\YiiDevTool\Command\Github;

use Github\Api\Repo;
use Github\Client;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\YiiDevTool\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\Component\Package\Package;

class SettingsCommand extends PackageCommand
{
    private InputInterface $input;
    private OutputInterface $output;

    protected function configure()
    {
        $this
            ->setName('github/settings')
            ->setDescription('Apply settings to GitHub repositories');

        $this->addPackageArgument();
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        parent::initialize($input, $output);
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<success>✔ Done</success>';
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, "Adjusting settings for {package}");

        $client = new Client();
        $client->authenticate($this->getToken(), null, Client::AUTH_ACCESS_TOKEN);
        $repoApi = new Repo($client);

        $repoApi->update($package->getVendor(), $package->getId(), $this->getSettings());
    }

    private function getSettings(): array
    {
        return require dirname(__DIR__, 3) . '/config/settings.php';
    }

    private function getToken(): string
    {
        $tokenFile = dirname(__DIR__, 3) . '/config/github.token';
        if (!file_exists($tokenFile)) {
            throw new \RuntimeException("There's no $tokenFile. Please create one and put your GitHub token there.");
        }

        return trim(file_get_contents($tokenFile));
    }
}
