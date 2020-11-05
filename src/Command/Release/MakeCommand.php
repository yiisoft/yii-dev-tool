<?php

namespace Yiisoft\YiiDevTool\Command\Release;

use GitWrapper\GitWorkingCopy;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Yiisoft\YiiDevTool\Component\Changelog;
use Yiisoft\YiiDevTool\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\Component\Package\Package;

class MakeCommand extends PackageCommand
{
    private InputInterface $input;
    private OutputInterface $output;

    private ?string $tag;

    private const MAIN_BRANCHES = ['master', 'main'];

    private const VERSION_MAJOR = 'major';
    private const VERSION_MINOR = 'minor';
    private const VERSION_PATCH = 'patch';

    private const VERSIONS = [self::VERSION_PATCH, self::VERSION_MINOR, self::VERSION_MAJOR];

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

        $nextVersion = $this->tag;
        if ($nextVersion === null) {
            $versionType = $this->choose('What release is it?', '%s is not a valid release type.', self::VERSIONS);
            $nextVersion = $this->getNextVersion($currentVersion, $versionType);
        }
        $io->info("Going to release $nextVersion.");

        if ($git->hasChanges()) {
            $changes = $git->getStatus();
            if ($this->confirm("You have uncommitted changes:\n" . $changes . "\nDiscard these?")) {
                $git->reset(['hard' => true]);
                $git->clean('-d', '-f');
            } else {
                $io->error('Can not continue with uncommitted changes.');
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

        if (!$git->isUpToDate()) {
            if ($this->confirm('Repository is not up to date. Update?')) {
                $git->pull();
            } else {
                $io->error('Can not continue with outdated repository.');
            }
        }

        $changelogPath = $package->getPath() . '/CHANGELOG.md';
        $changelog = new Changelog($changelogPath);
        $io->info("Sorting $changelogPath.");
        $changelog->resort($nextVersion);

        $io->info("Closing $changelogPath.");
        $changelog->close($currentVersion);

        $io->info("Committing changes.");
        $git->commit([
            'S' => true,
            'a' => true,
            'm' => "Release version $nextVersion"
        ]);

        $io->info('Adding a tag.');
        $git->tag([
            's' => $nextVersion,
            'm' => $nextVersion
        ]);

        $io->info("Opening $changelogPath.");
        $changelog->open($this->getNextVersion($nextVersion, self::VERSION_PATCH));

        $io->info("Committing changes.");
        $git->commit([
            'a' => true,
            'm' => "Prepare for next release"
        ]);

        if ($this->confirm('Push commits and tags?')) {
            $git->push();
            $git->pushTags();
        }

        $io->info('The following steps are left to do manually:');
        $io->info("- Close the $currentVersion <href=https://github.com/{$package->getName()}/milestones/>milestone on GitHub</> and open new one for $nextVersion.");
        //$io->info("- Create a release on GitHub.");
        $io->info("- Release news and announcement.");
        $io->done();
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

    private function getCurrentVersion(GitWorkingCopy $git): string
    {
        $tags = $git->tags()->all();
        rsort($tags, SORT_NATURAL); // TODO this can not deal with alpha/beta/rc...
        return reset($tags);
    }

    private function getNextVersion(string $currentVersion, string $type): string
    {
        $parts = explode('.', $currentVersion);
        switch ($type) {
            case self::VERSION_MAJOR:
                $parts[0]++;
                $parts[1] = 0;
                $parts[2] = 0;
                break;
            case self::VERSION_MINOR:
                $parts[1]++;
                $parts[2] = 0;
                break;
            case self::VERSION_PATCH:
                $parts[2]++;
                break;
            default:
                throw new \RuntimeException('Unknown version type.');
        }
        return implode('.', $parts);
    }
}
