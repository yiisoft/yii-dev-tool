<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Release;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\YiiDevTool\App\Component\Console\OutputManager;
use Yiisoft\YiiDevTool\App\Component\Console\YiiDevToolStyle;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\App\Component\Package\PackageList;
use Yiisoft\YiiDevTool\Infrastructure\Changelog;

#[AsCommand(
    name: 'release/missing',
    description: 'Find out which stable packages contain unreleased changes'
)]
final class MissingCommand extends Command
{
    private ?OutputManager $io = null;
    private ?PackageList $packageList = null;

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new OutputManager(new YiiDevToolStyle($input, $output));
    }

    protected function getIO(): OutputManager
    {
        if ($this->io === null) {
            throw new RuntimeException('IO is not initialized.');
        }

        return $this->io;
    }

    private function initPackageList(): void
    {
        $io = $this->getIO();

        try {
            $ownerPackages = require $this->getAppRootDir() . 'owner-packages.php';
            if (!preg_match('/^[a-z0-9][a-z0-9-]*[a-z0-9]$/i', $ownerPackages)) {
                $io->error([
                    'The packages owner can only contain the characters [a-z0-9-], and the character \'-\' cannot appear at the beginning or at the end.',
                    'See <file>owner-packages.php</file> to set the packages owner.',
                ]);

                exit(1);
            }

            $this->packageList = new PackageList(
                $ownerPackages,
                $this->getAppRootDir() . 'packages.php',
                $this->getAppRootDir() . 'dev',
            );
        } catch (InvalidArgumentException $e) {
            $io->error([
                'Invalid local package configuration <file>packages.local.php</file>',
                $e->getMessage(),
                'See <file>packages.local.php.example</file> for configuration examples.',
            ]);

            exit(1);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initPackageList();

        $packagesMissingRelease = [];

        $installedPackages = $this
            ->getPackageList()
            ->getInstalledAndEnabledPackages();

        // Get packages missing release.
        foreach ($installedPackages as $installedPackage) {
            $changelogFile = $installedPackage->getPath() . '/CHANGELOG.md';
            if ($this->hasRelease($installedPackage) && file_exists($changelogFile)) {
                $changelog = new Changelog($changelogFile);
                [$versionTitle, $changes] = $changelog->getReleaseLog();
                if (empty($changes) || $changes[0] === '- no changes in this release.') {
                    continue;
                }
                $tags = $installedPackage->getGitWorkingCopy()->tags()->all();
                rsort($tags, SORT_NATURAL);
                preg_match('/(\d+\.\d+\.\d+) under development/', $versionTitle[1], $matches);
                $packagesMissingRelease[$installedPackage->getName()] = [
                    'missing' => $matches[1],
                    'last' => reset($tags),
                ];
            }
        }

        $successStyle = new TableCellStyle(['fg' => 'green']);
        $packagesToRelease = [];

        foreach ($packagesMissingRelease as $packageName => $releases) {
            $packagesToRelease[] = [
                new TableCell($packageName, ['style' => $successStyle]),
                $releases['last'],
                $releases['missing'],
            ];
        }

        $tableIO = new Table($output);
        $tableIO->setHeaders(['Package', 'Last release', 'Missing release']);

        if (count($packagesToRelease) > 0) {
            $tableIO->addRows($packagesToRelease);
        }
        $tableIO->render();

        return Command::SUCCESS;
    }

    private function hasRelease(Package $package): bool
    {
        $gitWorkingCopy = $package->getGitWorkingCopy();
        foreach (
            $gitWorkingCopy
                ->tags()
                ->all() as $tag
        ) {
            if ($tag !== '') {
                return true;
            }
        }
        return false;
    }

    private function getPackageList(): PackageList
    {
        return $this->packageList;
    }

    /**
     * Use this method to get a root directory of the tool.
     *
     * Commands and components can be moved as a result of refactoring,
     * so you should not rely on their location in the file system.
     *
     * @return string Path to the root directory of the tool WITH a TRAILING SLASH.
     */
    protected function getAppRootDir(): string
    {
        return rtrim(
            $this
                    ->getApplication()
                    ->getRootDir(),
            DIRECTORY_SEPARATOR
        ) . DIRECTORY_SEPARATOR;
    }
}
