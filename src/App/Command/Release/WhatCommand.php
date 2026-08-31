<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Release;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Yiisoft\YiiDevTool\App\Component\Console\OutputManager;
use Yiisoft\YiiDevTool\App\Component\Console\YiiDevToolStyle;
use Yiisoft\YiiDevTool\App\Component\GitHubTokenAware;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\App\Component\Package\PackageList;
use Yiisoft\YiiDevTool\App\YiiDevToolApplication;
use Yiisoft\YiiDevTool\Infrastructure\Changelog;
use Yiisoft\YiiDevTool\Infrastructure\Composer\ComposerPackage;
use Yiisoft\YiiDevTool\Infrastructure\Composer\Config\ComposerConfig;

use function array_filter;
use function array_push;
use function array_unique;
use function array_values;
use function implode;
use function in_array;
use function is_file;
use function preg_match;
use function preg_match_all;
use function rsort;
use function sprintf;
use function trim;

use const DIRECTORY_SEPARATOR;
use const SORT_NATURAL;

/** @method YiiDevToolApplication getApplication() */
final class WhatCommand extends Command
{
    use GitHubTokenAware;

    private ?OutputManager $io = null;
    private ?PackageList $packageList = null;

    public function __construct(private ?GitHubReleaseInspectorInterface $gitHub = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('release:what')
            ->setDescription('Find packages that are ready to release');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new OutputManager(new YiiDevToolStyle($input, $output));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initPackageList();
        $ready = [];
        $blocked = [];

        foreach ($this->packageList->getInstalledAndEnabledPackages() as $package) {
            if (!$package->isGitRepositoryCloned()) {
                continue;
            }

            try {
                $candidate = $this->inspectCandidate($package);
            } catch (Throwable $e) {
                $blocked[] = [$package->getName(), '', $e->getMessage()];
                continue;
            }

            if ($candidate === null) {
                continue;
            }

            $row = [$package->getName(), $candidate['version'], implode("\n", $candidate['summary'])];
            if ($candidate['problems'] === []) {
                $ready[] = $row;
            } else {
                $blocked[] = [
                    $package->getName(),
                    $candidate['version'],
                    implode("\n", $candidate['problems']),
                ];
            }
        }

        $this->render($output, $ready, $blocked);

        return $blocked === [] ? Command::SUCCESS : Command::FAILURE;
    }

    protected function getIO(): OutputManager
    {
        if ($this->io === null) {
            throw new RuntimeException('IO is not initialized.');
        }

        return $this->io;
    }

    protected function getAppRootDir(): string
    {
        return rtrim($this->getApplication()->getRootDir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * @return array{version: string, summary: list<string>, problems: list<string>}|null
     */
    private function inspectCandidate(Package $package): ?array
    {
        $git = $package->getGitWorkingCopy();
        $tags = array_values(array_filter(
            $git->tags()->all(),
            static fn(string $tag): bool => preg_match('/^\d+\.\d+\.\d+$/D', $tag) === 1,
        ));
        rsort($tags, SORT_NATURAL);
        $latestTag = $tags[0] ?? null;
        $range = $latestTag === null ? 'HEAD' : "$latestTag..HEAD";

        if ((int) trim($git->run('rev-list', ['--count', $range])) === 0) {
            return null;
        }

        $problems = [];
        if ($git->hasChanges()) {
            $problems[] = 'Working tree is not clean.';
        }

        [$version, $summary, $changelogProblems] = $this->readChangelog($package);
        array_push($problems, ...$changelogProblems);
        array_push($problems, ...$this->checkComposer($package));

        $repositoryPackage = $package->getRootPackage() ?? $package;
        $vendor = $repositoryPackage->getVendor();
        $repository = $repositoryPackage->getId();
        $head = trim($git->run('rev-parse', ['HEAD']));
        $gitHub = $this->getGitHub();
        $defaultBranch = $gitHub->getDefaultBranch($vendor, $repository);
        if ($head !== $defaultBranch['sha']) {
            $problems[] = sprintf('HEAD does not match origin/%s.', $defaultBranch['branch']);
        }

        $checks = $gitHub->getCheckRuns($vendor, $repository, $head);
        if ($checks === []) {
            $problems[] = 'No GitHub checks found for HEAD.';
        }
        foreach ($checks as $check) {
            if ($check['status'] !== 'completed') {
                $problems[] = sprintf('GitHub check "%s" is %s.', $check['name'], $check['status']);
            } elseif (!in_array($check['conclusion'], ['success', 'skipped'], true)) {
                $problems[] = sprintf(
                    'GitHub check "%s" concluded with %s.',
                    $check['name'],
                    $check['conclusion'] ?? 'no result',
                );
            }
        }

        foreach ($this->extractIssueNumbers($summary) as $issue) {
            if ($gitHub->getIssueState($vendor, $repository, $issue) !== 'closed') {
                $problems[] = "Issue #$issue is still open.";
            }
        }

        return [
            'version' => $version,
            'summary' => $summary,
            'problems' => array_values(array_unique($problems)),
        ];
    }

    /** @return array{string, list<string>, list<string>} */
    private function readChangelog(Package $package): array
    {
        $path = $package->getPath() . '/CHANGELOG.md';
        if (!is_file($path)) {
            return ['', [], ['CHANGELOG.md is missing.']];
        }

        [$header, $changes] = (new Changelog($path))->getReleaseLog();
        $heading = $header[1] ?? '';
        if (!preg_match('/^## (\S+) under development$/', trim($heading), $matches)) {
            return ['', [], ['The under-development changelog heading is missing or invalid.']];
        }

        $normalizedChanges = [];
        foreach ($changes as $change) {
            if (trim($change) !== '') {
                $normalizedChanges[] = trim($change);
            }
        }
        $changes = $normalizedChanges;
        if ($changes === [] || $changes === ['- no changes in this release.']) {
            return [$matches[1], [], ['The under-development changelog has no changes.']];
        }

        return [$matches[1], $changes, []];
    }

    /** @return list<string> */
    private function checkComposer(Package $package): array
    {
        if (!$package->composerConfigFileExists()) {
            return [];
        }

        $config = (new ComposerPackage($package->getName(), $package->getPath()))->getComposerConfig();
        $unstable = ['dev', 'alpha', 'beta', 'rc'];
        $problems = [];
        $minimumStability = $config->getSection(ComposerConfig::SECTION_MINIMUM_STABILITY);
        if (in_array($minimumStability, $unstable, true)) {
            $problems[] = "Composer minimum-stability is $minimumStability.";
        }

        foreach ([ComposerConfig::SECTION_REQUIRE, ComposerConfig::SECTION_REQUIRE_DEV] as $section) {
            foreach ($config->getDependencyList($section)->getDependencies() as $dependency) {
                if ($dependency->constraintContainsAnyOfStabilityFlags($unstable)) {
                    $problems[] = sprintf(
                        'Dependency %s uses unstable constraint %s.',
                        $dependency->getPackageName(),
                        $dependency->getConstraint(),
                    );
                }
            }
        }

        return $problems;
    }

    /**
     * @param list<string> $summary
     * @return list<int>
     */
    private function extractIssueNumbers(array $summary): array
    {
        preg_match_all('/#(\d+)/', implode("\n", $summary), $matches);
        $issues = [];
        foreach ($matches[1] as $issue) {
            $issues[] = (int) $issue;
        }

        return array_values(array_unique($issues));
    }

    /**
     * @param list<array{string, string, string}> $ready
     * @param list<array{string, string, string}> $blocked
     */
    private function render(OutputInterface $output, array $ready, array $blocked): void
    {
        $table = new Table($output);
        $table->setHeaders(['Package', 'Version', 'Summary / problems']);
        $table->setColumnMaxWidth(2, 120);

        if ($ready !== []) {
            $table->addRow([
                new TableCell('Packages to release', [
                    'colspan' => 3,
                    'style' => new TableCellStyle(['align' => 'center', 'bg' => 'green']),
                ]),
            ]);
            $table->addRows($ready);
        }
        if ($blocked !== []) {
            $table->addRow([
                new TableCell('Blocked packages', [
                    'colspan' => 3,
                    'style' => new TableCellStyle(['align' => 'center', 'bg' => 'red']),
                ]),
            ]);
            $table->addRows($blocked);
        }

        $table->render();
    }

    private function getGitHub(): GitHubReleaseInspectorInterface
    {
        return $this->gitHub ??= new GitHubReleaseInspector($this->getGitHubToken());
    }

    private function initPackageList(): void
    {
        try {
            $owner = require $this->getAppRootDir() . 'owner-packages.php';
            if (!preg_match('/^[a-z0-9][a-z0-9-]*[a-z0-9]$/i', $owner)) {
                throw new InvalidArgumentException('Invalid packages owner in owner-packages.php.');
            }

            $packagesRootDir = $this->getApplication()->getConfig('packagesRootDir')
                ?? $this->getAppRootDir() . 'dev';
            $this->packageList = new PackageList(
                $owner,
                $this->getAppRootDir() . 'packages.php',
                $packagesRootDir,
            );
        } catch (InvalidArgumentException $e) {
            $this->getIO()->error($e->getMessage());
            throw $e;
        }
    }
}
