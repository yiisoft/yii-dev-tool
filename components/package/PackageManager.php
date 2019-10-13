<?php

namespace yiidev\components\package;

use yiidev\components\console\Color;
use yiidev\components\console\Executor;
use yiidev\components\console\Printer;
use yiidev\components\helpers\FileHelper;

class PackageManager
{
    /** @var Printer */
    private $printer;

    /** @var Executor */
    private $executor;

    public function __construct(Printer $printer)
    {
        $this->printer = $printer;
        $this->executor = new Executor();
    }

    public function showGitStatus(Package $package): void
    {
        $printer = $this->printer;

        $command = 'cd ' . escapeshellarg($package->getPath()) . ' && git status -s';
        $output = $this->executor->execute($command)->getLastOutput();

        if (empty($output)) {
            $printer
                ->stdoutln($package->getName(), Color::GREEN);
        } else {
            $printer
                ->stdoutln($package->getName(), Color::YELLOW)
                ->stdoutln($output)
                ->stdoutln();
        }
    }

    public function showGitStatuses(PackageList $packageList): void
    {
        foreach ($packageList->getAllPackages() as $package) {
            $this->showGitStatus($package);
        }
    }

    private function gitClone(Package $package, bool $useHttp): void
    {
        $printer = $this->printer;

        $printer
            ->stdout('Cloning package ')
            ->stdout($package->getName(), Color::YELLOW);

        if (file_exists($package->getPath())) {
            $printer->stdoutln(' - already cloned', Color::GREEN);

            return;
        }

        $printer->stdoutln('...');

        $repo = ($useHttp ? 'https://github.com/' : 'git@github.com:') . $package->getName() . '.git';
        $command = 'git clone ' . escapeshellarg($repo) . ' ' . escapeshellarg($package->getPath());
        $output = $this->executor->execute($command)->getLastOutput();

        $printer
            ->stdoutln($output)
            ->stdoutln('Done.', Color::GREEN);
    }

    private function removeSymbolicLinks(Package $package): void
    {
        foreach (FileHelper::findDirectoriesIn("{$package->getPath()}/vendor/yiisoft") as $yiisoftPackage) {
            if (is_link($link = "{$package->getPath()}/vendor/yiisoft/$yiisoftPackage")) {
                FileHelper::unlink($link);
            }
        }
    }

    private function composerInstall(Package $package, $useUpdateCommand = false): void
    {
        $printer = $this->printer;
        $composerCommandName = $useUpdateCommand ? 'update' : 'install';

        if (!is_file("{$package->getPath()}/composer.json")) {
            $printer
                ->stdout('No composer.json in ')
                ->stdout($package->getName(), Color::YELLOW)
                ->stdoutln(", skipping composer $composerCommandName.");

            return;
        }

        $printer
            ->stdout("composer $composerCommandName in ")
            ->stdout($package->getName(), Color::YELLOW)
            ->stdoutln('...');

        $command =
            "composer $composerCommandName --prefer-dist --no-progress --working-dir " .
            escapeshellarg($package->getPath()) .
            ($this->printer->isColorsEnabled() ? ' --ansi' : ' --no-ansi');

        $output = $this->executor->execute($command)->getLastOutput();
        $printer
            ->stdoutln($output)
            ->stdoutln('Done.', Color::GREEN);

        if ($this->executor->hasErrorOccurred()) {
            $package->setError($this->executor->getLastOutput());
        }
    }

    public function install(Package $package, bool $useHttp): void
    {
        $this->gitClone($package, $useHttp);
        $this->removeSymbolicLinks($package);
        $this->composerInstall($package);
    }

    public function installAll(PackageList $packageList, bool $useHttp): void
    {
        foreach ($packageList->getAllPackages() as $package) {
            $this->install($package, $useHttp);
        }
    }

    public function update(Package $package, bool $useHttp): void
    {
        $this->gitClone($package, $useHttp);
        $this->removeSymbolicLinks($package);
        $this->composerInstall($package, true);
    }

    public function updateAll(PackageList $packageList, bool $useHttp): void
    {
        foreach ($packageList->getAllPackages() as $package) {
            $this->update($package, $useHttp);
        }
    }

    /**
     * @param Package $package
     * @param Package[] $installedPackages
     */
    private function linkPackages(Package $package, array $installedPackages): void
    {
        foreach ($installedPackages as $installedPackage) {
            if ($package->getName() === $installedPackage->getName()) {
                continue;
            }

            $installedPackagePath = "{$package->getPath()}/vendor/{$installedPackage->getName()}";
            if (file_exists($installedPackagePath)) {
                // rm dir and replace it with link
                FileHelper::removeDirectory($installedPackagePath);
                symlink($installedPackage->getPath(), $installedPackagePath);
            }
        }
    }

    public function createSymbolicLinks(PackageList $list): void
    {
        $printer = $this->printer;

        $printer->stdoutln('Re-linking vendor directories...');
        foreach ($list->getAllPackages() as $package) {
            $printer->stdoutln($package->getName());
            $this->linkPackages($package, $list->getInstalledPackages());
        }
        $printer->stdoutln('Done.', Color::GREEN);
    }

    public function replicateToPackage(Package $package, ReplicationSource $replicationSource): void
    {
        $printer = $this->printer;

        if ($package->getName() === $replicationSource->getName()) {
            $printer->stderrln('Cannot replicate into itself.');

            exit(1);
        }

        $printer->stdout("{$package->getName()} ", Color::GREEN);

        if (!file_exists($package->getPath())) {
            $printer->stdoutln('❌');

            return;
        }

        foreach ($replicationSource->getSourceFiles() as $sourceFile) {
            FileHelper::copy(
                $replicationSource->getPath() . DIRECTORY_SEPARATOR . $sourceFile,
                $package->getPath() . DIRECTORY_SEPARATOR . $sourceFile
            );
        }

        $printer->stdoutln('✔');
    }

    public function replicateToPackages(PackageList $packageList, ReplicationSource $replicationSource): void
    {
        foreach ($packageList->getAllPackages() as $package) {
            if ($package->getName() === $replicationSource->getName()) {
                continue;
            }

            $this->replicateToPackage($package, $replicationSource);
        }
    }

    public function gitCommit(Package $package, string $commitMessage): void
    {
        $printer = $this->printer;

        $printer
            ->stdout("Committing ")
            ->stdout($package->getName(), Color::GREEN)
            ->stdoutln('...');

        $command =
            'cd ' . escapeshellarg($package->getPath()) .
            ' && git add . && git commit -m ' . escapeshellarg($commitMessage);

        $output = $this->executor->execute($command)->getLastOutput();

        $printer
            ->stdoutln($output)
            ->stdoutln();
    }

    public function gitCommitAll(PackageList $packageList, string $commitMessage): void
    {
        foreach ($packageList->getAllPackages() as $package) {
            $this->gitCommit($package, $commitMessage);
        }
    }

    public function gitPush(Package $package): void
    {
        $printer = $this->printer;

        $printer
            ->stdout("Pushing ")
            ->stdout($package->getName(), Color::GREEN)
            ->stdoutln('...');

        $command = 'cd ' . escapeshellarg($package->getPath()) . ' && git push';
        $output = $this->executor->execute($command)->getLastOutput();

        $printer
            ->stdoutln($output)
            ->stdoutln();
    }

    public function gitPushAll(PackageList $packageList): void
    {
        foreach ($packageList->getAllPackages() as $package) {
            $this->gitPush($package);
        }
    }

    public function gitPull(Package $package): void
    {
        $printer = $this->printer;

        $printer
            ->stdout("Pulling ")
            ->stdout($package->getName(), Color::GREEN)
            ->stdoutln('...');

        $command = 'cd ' . escapeshellarg($package->getPath()) . ' && git pull';
        $output = $this->executor->execute($command)->getLastOutput();

        $printer
            ->stdoutln($output)
            ->stdoutln();
    }

    public function gitPullAll(PackageList $packageList): void
    {
        foreach ($packageList->getAllPackages() as $package) {
            $this->gitPull($package);
        }
    }

    public function showPackageErrors(PackageList $packageList): void
    {
        $printer = $this->printer;
        $packagesWithError = $packageList->getPackagesWithError();

        if (count($packagesWithError)) {
            $printer
                ->stdoutln()
                ->stdoutln('Some packages have dependency issues...', Color::LIGHT_RED)
                ->stdoutln();

            foreach ($packagesWithError as $package) {
                $printer
                    ->stdout('Errors of ')
                    ->stdout($package->getName(), Color::YELLOW)
                    ->stdoutln(' package:')
                    ->stdoutln()
                    ->stdoutln($package->getError())
                    ->stdoutln();
            }
        }
    }

    public function lint(Package $package, string $codeSnifferBinPath): void
    {
        $printer = $this->printer;
        $executor = $this->executor;

        $printer
            ->stdout("Checking ")
            ->stdout($package->getName(), Color::YELLOW)
            ->stdoutln('...');

        $command =
            $codeSnifferBinPath . ' ' .
            escapeshellarg($package->getPath()) . ' ' .
            ($printer->isColorsEnabled() ? '--colors ' : '') .
            '--standard=PSR2 --ignore=*/vendor/*,*/docs/*';

        $output = $executor->execute($command)->getLastOutput();

        // CodeSniffer exits with an error code if it finds problems
        if ($executor->hasErrorOccurred()) {
            $printer
                ->stdoutln($output)
                ->stdoutln();
        } else {
            $printer
                ->stdoutln()
                ->stdoutln('No problems found ✔', Color::GREEN)
                ->stdoutln();
        }
    }

    public function lintAll(PackageList $packageList, string $codeSnifferBinPath): void
    {
        foreach ($packageList->getAllPackages() as $package) {
            $this->lint($package, $codeSnifferBinPath);
        }
    }
}
