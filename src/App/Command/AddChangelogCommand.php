<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symplify\GitWrapper\GitWorkingCopy;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\Infrastructure\Changelog;
use Yiisoft\YiiDevTool\Infrastructure\Version;

final class AddChangelogCommand extends PackageCommand
{
    private string $message = '';
    private string $type = '';
    private string $prId = '';

    protected function configure()
    {
        $this
            ->setName('changelog/add')
            ->setDescription('Add an changelog entry')
            ->addArgument('type', InputArgument::REQUIRED, 'Entry text', null)
            ->addArgument('pull-request-id', InputArgument::REQUIRED, 'Entry text')
            ->addArgument('message', InputArgument::REQUIRED, 'Entry text')

        ;

        parent::configure();
    }

    protected function beforeProcessingPackages(InputInterface $input): void
    {
        $this->message = $input->getArgument('message');
        $this->type = $input->getArgument('type');
        $this->prId = $input->getArgument('pull-request-id');
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<success>✔ Done</success>';
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();

        $io->preparePackageHeader($package, 'Adding the changelog entry to {package}');
        $git = $package->getGitWorkingCopy();

        $currentVersion = $this->getCurrentVersion($git);
        if ($currentVersion->asString() === '') {
            $io->info('There is currently no release.');
        } else {
            $io->info("Current version is $currentVersion.");
        }

        $changelogPath = $package->getPath() . '/CHANGELOG.md';
        $changelog = new Changelog($changelogPath);

        $io->info("Sorting \"$changelogPath\".");
        $changelog->resort();

        $io->info("Adding the entry into \"$changelogPath\".");
        $type = ucfirst($this->type);
        $message = "{$type} #{$this->prId}: {$this->message}";
        $changelog->addEntry($message);
    }

    private function getCurrentVersion(GitWorkingCopy $git): Version
    {
        $tags = $git
            ->tags()
            ->all();
        rsort($tags, SORT_NATURAL); // TODO this can not deal with alpha/beta/rc...
        return new Version(reset($tags));
    }
}
