<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Release;

use Github\Api\Repository\Releases;
use Github\AuthMethod;
use Github\Client;
use RuntimeException;
use Symplify\GitWrapper\GitWorkingCopy;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\Infrastructure\Changelog;
use Yiisoft\YiiDevTool\Infrastructure\Composer\ComposerPackage;
use Yiisoft\YiiDevTool\Infrastructure\Composer\Config\ComposerConfig;
use Yiisoft\YiiDevTool\Infrastructure\Version;

use function in_array;

final class MakeCommand extends PackageCommand
{
    private ?string $tag = null;

    private const MAIN_BRANCHES = ['master', 'main'];

    protected function configure()
    {
        $this
            ->setName('release/make')
            ->setDescription('Make a package release')
            ->addOption('tag', null, InputArgument::OPTIONAL, 'Version to tag');

        parent::configure();
    }

    protected function beforeProcessingPackages(InputInterface $input): void
    {
        $io = $this->getIO();

        $this->tag = $input->getOption('tag');

        if ($io->getVerbosity() < OutputInterface::VERBOSITY_VERBOSE) {
            $io->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        }
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<success>✔ Done</success>';
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, 'Releasing {package}');
        $git = $package->getGitWorkingCopy();

        if (!$package->composerConfigFileExists()) {
            $io->warning([
                "No <file>composer.json</file> in package <package>{$package->getName()}</package>.",
                'Release cancelled.',
            ]);

            return;
        }

        $composerPackage = new ComposerPackage($package->getName(), $package->getPath());
        $composerConfig = $composerPackage->getComposerConfig();

        $unstableFlags = ['dev', 'alpha', 'beta', 'rc'];

        $minimumStability = $composerConfig->getSection(ComposerConfig::SECTION_MINIMUM_STABILITY);
        if (in_array($minimumStability, $unstableFlags, true)) {
            $io->warning([
                "Minimum-stability of package <package>{$package->getName()}</package> is <em>$minimumStability</em>.",
                'Release is only possible for stable packages.',
                'Releasing skipped.',
            ]);

            return;
        }

        $tokenFile = $this->getAppRootDir() . 'config/github.token';
        if (!file_exists($tokenFile)) {
            $io->warning([
                "There's no $tokenFile. Please create one and put your GitHub token there.",
                'Token is required to create release on GitHub.',
                "You may create it here: https://github.com/settings/tokens. Choose 'repo' rights.",
                'Release cancelled.',
            ]);

            return;
        }

        $dependencyList = $composerConfig->getDependencyList(ComposerConfig::SECTION_REQUIRE);
        foreach ($dependencyList->getDependencies() as $dependency) {
            if ($dependency->constraintContainsAnyOfStabilityFlags($unstableFlags)) {
                $io->warning([
                    "Constraint of dependency <em>{$dependency->getPackageName()}</em> contains an unstable flag.",
                    "The constraint is <em>{$dependency->getConstraint()}</em>.",
                    'Release is only possible for packages with stable dependencies.',
                    'Releasing skipped.',
                ]);

                return;
            }
        }

        $io->info("Hurray, another release is coming!\n");

        $currentVersion = $this->getCurrentVersion($git);
        if ($currentVersion->asString() === '') {
            $io->info('There is currently no release.');
        } else {
            $io->info("Current version is $currentVersion.");
        }


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
        $changelog->resort();

        $io->info("Closing $changelogPath for $versionToRelease.");
        $changelog->close($versionToRelease);

        $io->info("Committing changes for $versionToRelease.");
        $git->commit([
            'S' => true,
            'a' => true,
            'm' => "Release version $versionToRelease",
        ]);

        $io->info("Adding a tag for $versionToRelease.");
        $git->tag([
            's' => (string) $versionToRelease,
            'm' => (string) $versionToRelease,
        ]);

        $nextVersion = $versionToRelease->getNext(Version::TYPE_PATCH);
        $io->info("Opening $changelogPath for $nextVersion.");
        $changelog->open($nextVersion);

        $io->info('Committing changes.');
        $git->commit([
            'a' => true,
            'm' => 'Prepare for next release',
        ]);

        if ($this->confirm('Push commits and tags, and release on GitHub?')) {
            $git->push();
            $git->pushTags();

            $this->releaseOnGithub($package, $versionToRelease);

            $io->done();

            $io->info('The following steps are left to do manually:');
            $io->info("- Close the $versionToRelease <href=https://github.com/{$package->getName()}/milestones/>milestone on GitHub</> and open new one for $nextVersion.");
            $io->info('- Release news and announcement.');

            $this->displayReleaseSummary(
                package: $package,
                composerConfig: $composerConfig,
                changelog: $changelog,
                versionToRelease: $versionToRelease
            );
        }
    }

    private function confirm(string $message): bool
    {
        return $this
            ->getIO()
            ->confirm($message, false);
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
        $tags = $git
            ->tags()
            ->all();
        rsort($tags, SORT_NATURAL); // TODO this can not deal with alpha/beta/rc...
        return new Version(reset($tags));
    }

    private function getVersionToRelease(Version $currentVersion): Version
    {
        if ($this->tag === null) {
            $versionType = $this
                ->getIO()
                ->choice('What release is it?', Version::TYPES);
            $nextVersion = $currentVersion->getNext($versionType);
        } else {
            $nextVersion = new Version($this->tag);
        }
        return $nextVersion;
    }

    private function releaseOnGithub(Package $package, Version $versionToRelease): void
    {
        $io = $this->getIO();
        $io->info("Creating release on GitHub for $versionToRelease.\n");

        $client = new Client();
        try {
            $token = $this->getToken();
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            $io->warning('Skipped creating release on GitHub.');
            return;
        }
        $changelogPath = $package->getPath() . '/CHANGELOG.md';
        $changelog = new Changelog($changelogPath);
        $client->authenticate($token, null, AuthMethod::ACCESS_TOKEN);
        $release = new Releases($client);
        $release->create($package->getVendor(), $package->getId(), [
            'name' => sprintf('Version %s', $versionToRelease),
            'tag_name' => $versionToRelease->asString(),
            'body' => implode("\n", $changelog->getReleaseNotes($versionToRelease)),
            'draft' => false,
            'prerelease' => false, // TODO: check if this is pre-release
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
    private function displayReleaseSummary(Package $package, ComposerConfig $composerConfig, Changelog $changelog, Version $versionToRelease): void
    {
        $packageName = $package->getName();

        $description = $composerConfig->getSection(ComposerConfig::SECTION_DESCRIPTION);

        $io = $this->getIO();
        $io->info("\n---\n");
        $io->info("<fg=green>" . $description . ' ' . $versionToRelease . "</>\n");

        $changes = [];

        foreach ($changelog->getReleaseNotes($versionToRelease) as $note) {
            $note = trim($note);
            if ($note === '') {
                continue;
            }

            $changes[] = '- ' . preg_replace('~^-.*?:\s+(.*)\s+\(?.*?\)$~', '$1', $note);
        }

        $changes = implode("\n", $changes);

        $text = <<<TEXT
        [$description](https://github.com/yiisoft/$packageName) version $versionToRelease was released.
        In this version:

        $changes
        TEXT;

        $io->info($text . "\n");
    }
}
