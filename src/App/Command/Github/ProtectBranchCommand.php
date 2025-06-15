<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Github;

use Github\Api\Repository\Protection;
use Github\AuthMethod;
use Github\Client;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\App\Component\GitHubTokenAware;

final class ProtectBranchCommand extends PackageCommand
{
    use GitHubTokenAware;

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
        $client->authenticate($this->getGitHubToken(), null, AuthMethod::ACCESS_TOKEN);
        $protectionApi = (new Protection($client));

        // See https://docs.github.com/en/rest/reference/repos#update-branch-protection
        $protectionApi->update($package->getVendor(), $package->getId(), $this->branch, [
            'required_status_checks' => null,
            'enforce_admins' => true,
            'required_pull_request_reviews' => null,
            'restrictions' => null,
        ]);
    }
}
