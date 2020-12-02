<?php

namespace Yiisoft\YiiDevTool\Command\Release;

use GitWrapper\GitWorkingCopy;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Yiisoft\YiiDevTool\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\Component\Package\Package;
use Yiisoft\YiiDevTool\Infrastructure\Changelog;
use Yiisoft\YiiDevTool\Infrastructure\Version;

class MakeCommand extends PackageCommand
{
    private InputInterface $input;
    private OutputInterface $output;

    private ?string $tag;

    private const MAIN_BRANCHES = ['master', 'main'];

    protected function configure()
    {
        $this
            ->setName('release/make')
            ->setDescription('Make a package release')
            ->addOption('tag', null, InputArgument::OPTIONAL, 'Version to tag');

        $this->addPackageArgument();
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        parent::initialize($input, $output);
    }

    protected function beforeProcessingPackages(InputInterface $input): void
    {
        $this->tag = $input->getOption('tag');

        if ($this->output->getVerbosity() < OutputInterface::VERBOSITY_VERBOSE) {
            $this->output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        }
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<success>✔ Done</success>';
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, "Releasing {package}");
        $git = $package->getGitWorkingCopy();

        $io->info("Hurray, another release is coming!\n");

        $currentVersion = $this->getCurrentVersion($git);
        $io->info("Current version is $currentVersion.");

        $versionToRelease = $this->getVersionToRelease($currentVersion);
        $io->info("Going to release $versionToRelease.");

        if ($git->hasChanges()) {
            $changes = $git->getStatus();
            if ($this->confirm("You have uncommitted changes:\n" . $changes . "\nDiscard these?")) {
                $git->reset(['hard' => true]);
                $git->clean('-d', '-f');
            } else {
                $io->error('Can not continue with uncommitted changes.');
                return;
            }
        }

        $currentBranch = $this->getCurrentBranch($git);
        if (!in_array($currentBranch, self::MAIN_BRANCHES, true)) {
            $mainBranch = $this->getMainBranch($git);

            if ($mainBranch === null) {
                if (!$this->confirm("You are going to release from \"$currentBranch\" branch. OK?")) {
                    return;
                }
            } elseif ($this->confirm("You are going to release from \"$currentBranch\" branch. Want to switch to \"$mainBranch\"?")) {
                $git->checkout($mainBranch);
            }
        }

        $io->info('Pulling latest changes.');
        $git->pull();

        $changelogPath = $package->getPath() . '/CHANGELOG.md';
        $changelog = new Changelog($changelogPath);

        $io->info("Sorting $changelogPath for $versionToRelease.");
        $changelog->resort($versionToRelease);

        $io->info("Closing $changelogPath for $versionToRelease.");
        $changelog->close($versionToRelease);

        $io->info("Committing changes for $versionToRelease.");
        $git->commit([
            'S' => true,
            'a' => true,
            'm' => "Release version $versionToRelease"
        ]);

        $io->info("Adding a tag for $versionToRelease.");
        $git->tag([
            's' => $versionToRelease,
            'm' => $versionToRelease
        ]);

        $nextVersion = $versionToRelease->getNext(Version::TYPE_PATCH);
        $io->info("Opening $changelogPath for $nextVersion.");
        $changelog->open($nextVersion);

        $io->info('Committing changes.');
        $git->commit([
            'a' => true,
            'm' => 'Prepare for next release'
        ]);

        if ($this->confirm('Push commits and tags?')) {
            $git->push();
            $git->pushTags();

            $io->done();

            $io->info('The following steps are left to do manually:');
            $io->info("- Close the $currentVersion <href=https://github.com/{$package->getName()}/milestones/>milestone on GitHub</> and open new one for $versionToRelease.");
            //$io->info("- Create a release on GitHub.");
            $io->info('- Release news and announcement.');
        }
    }

    private function confirm(string $message): bool
    {
        $question = new ConfirmationQuestion($message, false);
        return $this->getHelper('question')->ask($this->input, $this->output, $question);
    }

    private function choose(string $message, string $error, array $variants): string
    {
        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion(
            $message,
            $variants,
            0
        );
        $question->setErrorMessage($error);
        return $helper->ask($this->input, $this->output, $question);
    }

    private function getCurrentBranch(GitWorkingCopy $git): string
    {
        return trim($git->branch(['show-current' => true]));
    }

    private function getMainBranch(GitWorkingCopy $git): ?string
    {
        foreach ($git->getBranches() as $branch) {
            if (in_array($branch, self::MAIN_BRANCHES, true)) {
                return $branch;
            }
        }
        return null;
    }

    private function getCurrentVersion(GitWorkingCopy $git): Version
    {
        $tags = $git->tags()->all();
        rsort($tags, SORT_NATURAL); // TODO this can not deal with alpha/beta/rc...
        return new Version(reset($tags));
    }

    private function getVersionToRelease(Version $currentVersion): Version
    {
        if ($this->tag === null) {
            $versionType = $this->choose('What release is it?', '%s is not a valid release type.', Version::TYPES);
            $nextVersion = $currentVersion->getNext($versionType);
        } else {
            $nextVersion = new Version($this->tag);
        }
        return $nextVersion;
    }
}
