<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symplify\GitWrapper\GitWorkingCopy;
use Yiisoft\Injector\InvalidArgumentException;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\Infrastructure\Changelog;
use Yiisoft\YiiDevTool\Infrastructure\Version;

final class AddChangelogCommand extends PackageCommand
{
    private string $message = '';
    private string $type = '';
    private ?string $prId = '';

    protected function configure()
    {
        $this
            ->setName('changelog/add')
            ->setDescription('Add an changelog entry')
            ->addArgument('type', InputArgument::REQUIRED, 'Change type', null, Changelog::TYPES)
            ->addArgument('message', InputArgument::REQUIRED, 'Entry text')
            ->addOption('pull-request-id', 'pr', InputArgument::OPTIONAL, 'Pull request ID', null)
        ;

        parent::configure();
    }

    protected function beforeProcessingPackages(InputInterface $input): void
    {
        $this->message = $input->getArgument('message');
        $this->type = $input->getArgument('type');
        $this->prId = $input->getOption('pull-request-id');
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<success>✔ Done</success>';
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();

        $loweredTypes = array_map(fn (string $type) => strtolower($type), Changelog::TYPES);
        if (!in_array(strtolower($this->type), $loweredTypes, true)) {
            $io->error(
                sprintf(
                    'The type argument value must be one of the following: %s. "%s" given',
                    implode(', ', Changelog::TYPES),
                    $this->type
                )
            );
            return;
        }

        $io->preparePackageHeader($package, 'Adding the changelog entry to {package}');
        $git = $package->getGitWorkingCopy();

        $currentVersion = $this->getCurrentVersion($git);
        if ($currentVersion->asString() === '') {
            $io->info('There is currently no release.');
        } else {
            $io->info("Current version is $currentVersion.");
        }

        $messageParts = [];
        $messageParts[] = ucfirst($this->type);
        if ($this->prId) {
            $messageParts[] = ' #' . $this->prId;
        }
        $messageParts[] = ': ';
        $messageParts[] = $this->message;

        $text = implode('', $messageParts);

        $changelogPath = $package->getPath() . '/CHANGELOG.md';
        $changelog = new Changelog($changelogPath);

        $io->info(sprintf(
            'Adding the entry "%s" into "%s".',
            $text,
            $changelogPath,
        ));
        try {
            $changelog->addEntry($text);
        } catch (\InvalidArgumentException $e) {
            $io->error($e);
        }
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
