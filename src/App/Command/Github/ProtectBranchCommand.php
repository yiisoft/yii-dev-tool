<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Github;

use Github\Api\Repository\Protection;
use Github\Client;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;

final class ProtectBranchCommand extends PackageCommand
{
    private ?string $branch = null;

    protected function configure()
    {
        $this
            ->setName('github/protect-branch')
            ->setDescription('Protect specified branch for specified GitHub repositories')
            ->addArgument('branch', InputArgument::REQUIRED, 'Branch to protect');

        parent::configure();
    }

    protected function beforeProcessingPackages(InputInterface $input): void
    {
        $this->branch = $input->getArgument('branch');
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<success>✔ Done</success>';
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, 'Protecting {package}');

        $client = new Client();
        $client->authenticate($this->getToken(), null, Client::AUTH_ACCESS_TOKEN);
        $protectionApi = (new Protection($client));

        // See https://docs.github.com/en/rest/reference/repos#update-branch-protection
        $protectionApi->update($package->getVendor(), $package->getId(), $this->branch, [
            'required_status_checks' => null,
            'enforce_admins' => true,
            'required_pull_request_reviews' => null,
            'restrictions' => null,
        ]);
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
