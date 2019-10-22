<?php

namespace yiidev\commands;

use yiidev\components\console\Color;
use yiidev\components\console\Printer;
use yiidev\components\helpers\FileHelper;
use yiidev\components\package\Package;
use yiidev\components\package\PackageCommand;

class InstallCommand extends PackageCommand
{
    /** @var bool */
    private $updateMode;

    public function __construct(Printer $printer, bool $updateMode, string $commaSeparatedPackageIds = null)
    {
        parent::__construct($printer, $commaSeparatedPackageIds);
        $this->updateMode = $updateMode;
    }

    public function run(): void
    {
        foreach ($this->getTargetPackages() as $package) {
            $this->install($package);
        }

        $this->createSymbolicLinks();
        $this->showPackageErrors();
    }

    private function gitClone(Package $package): void
    {
        $printer = $this->getPrinter();
        $executor = $this->getExecutor();

        $printer
            ->stdout('Cloning package ')
            ->stdout($package->getId(), Color::CYAN)
            ->stdoutln(' repository...');

        if ($package->isGitRepositoryCloned()) {
            $printer
                ->stdoutln('The package already contains .git directory.', Color::YELLOW)
                ->stdoutln('Cloning skipped.', Color::YELLOW)
                ->stdoutln();

            return;
        }

        $printer
            ->stdout('Repository url: ')
            ->stdoutln($package->getRepositoryUrl(), Color::LIGHT_BLUE);

        $repo = $package->getRepositoryUrl();
        $command = 'git clone ' . escapeshellarg($repo) . ' ' . escapeshellarg($package->getPath());
        $output = $executor->execute($command)->getLastOutput();

        $printer->stdoutln($output);

        if ($executor->hasErrorOccurred()) {
            $package->setError($executor->getLastOutput(), 'cloning package repository');
            $printer
                ->stdoutln()
                ->stderr('An error occurred during cloning package ', Color::LIGHT_RED)
                ->stderr($package->getId(), Color::CYAN)
                ->stderrln(' repository.', Color::LIGHT_RED)
                ->stderrln($this->updateMode ? 'Package update aborted.' : 'Package installation aborted.', Color::LIGHT_RED)
                ->stderrln();
        } else {
            $printer
                ->stdoutln('✔ Done.', Color::GREEN)
                ->stdoutln();
        }
    }

    private function removeSymbolicLinks(Package $package): void
    {
        $printer = $this->getPrinter();

        $printer->stdoutln('Removing old package symlinks...');

        foreach (FileHelper::findDirectoriesIn("{$package->getPath()}/vendor/yiisoft") as $directoryName) {
            $directoryPath = "{$package->getPath()}/vendor/yiisoft/$directoryName";

            if (is_link($directoryPath)) {
                $printer
                    ->stdout('Removing symlink: ')
                    ->stdoutln($directoryPath, Color::LIGHT_BLUE);

                if (FileHelper::unlink($directoryPath) === false) {
                    $package->setError('Unable to remove symlink ' . $directoryPath, 'removing old package symlinks');

                    $printer
                        ->stdoutln()
                        ->stderrln('An error occurred during removing symlink ' . $directoryPath, Color::LIGHT_RED)
                        ->stderrln($this->updateMode ? 'Package update aborted.' : 'Package installation aborted.', Color::LIGHT_RED)
                        ->stderrln();

                    return;
                }
            }
        }

        $printer
            ->stdoutln('✔ Done.', Color::GREEN)
            ->stdoutln();
    }

    private function composerInstall(Package $package): void
    {
        $printer = $this->getPrinter();
        $executor = $this->getExecutor();

        $composerCommandName = $this->updateMode ? 'update' : 'install';

        $printer
            ->stdout("Running `composer $composerCommandName` in package ")
            ->stdout($package->getId(), Color::CYAN)
            ->stdoutln('...');

        if (!file_exists("{$package->getPath()}/composer.json")) {
            $printer
                ->stdout('No composer.json in package ', Color::YELLOW)
                ->stdout($package->getId(), Color::CYAN)
                ->stdoutln('.', Color::YELLOW)
                ->stdoutln("Running `composer $composerCommandName` skipped.", Color::YELLOW)
                ->stdoutln();

            return;
        }

        $command =
            "composer $composerCommandName --prefer-dist --no-progress --working-dir " .
            escapeshellarg($package->getPath()) .
            ($printer->isColorsEnabled() ? ' --ansi' : ' --no-ansi');

        $output = $executor->execute($command)->getLastOutput();

        $printer->stdoutln($output);

        if ($executor->hasErrorOccurred()) {
            $package->setError($executor->getLastOutput(), "running `composer $composerCommandName`");
            $printer
                ->stdoutln()
                ->stderrln("An error occurred during running `composer $composerCommandName`.", Color::LIGHT_RED)
                ->stderrln($this->updateMode ? 'Package update aborted.' : 'Package installation aborted.', Color::LIGHT_RED)
                ->stderrln();
        } else {
            $printer
                ->stdoutln('✔ Done.', Color::GREEN)
                ->stdoutln();
        }
    }

    private function printOperationHeader(Package $package): void
    {
        $this->getPrinter()
            ->stdoutln('-----------------------------------------------------------------------')
            ->stdout($this->updateMode ? 'Updating package ' : 'Installing package ')
            ->stdoutln($package->getId(), Color::CYAN)
            ->stdoutln('-----------------------------------------------------------------------')
            ->stdoutln();
    }

    private function install(Package $package): void
    {
        $printer = $this->getPrinter();

        if ($package->disabled()) {
            if ($this->areTargetPackagesSpecifiedExplicitly()) {
                $this->printOperationHeader($package);
                $printer
                    ->stdout('Package ', Color::YELLOW)
                    ->stdout($package->getId(), Color::CYAN)
                    ->stdoutln(' disabled by configuration.', Color::YELLOW)
                    ->stdoutln('Skipped.', Color::YELLOW)
                    ->stdoutln();
            }

            return;
        }

        $this->printOperationHeader($package);

        $hasGitRepositoryAlreadyBeenCloned = $package->isGitRepositoryCloned();

        if (!$this->updateMode || !$hasGitRepositoryAlreadyBeenCloned) {
            $this->gitClone($package);

            if ($package->hasError()) {
                return;
            }
        }

        if ($hasGitRepositoryAlreadyBeenCloned) {
            $this->removeSymbolicLinks($package);

            if ($package->hasError()) {
                return;
            }
        }

        $this->composerInstall($package);
    }

    /**
     * @param Package $package
     * @param Package[] $installedPackages
     */
    private function linkPackages(Package $package, array $installedPackages): void
    {
        foreach ($installedPackages as $installedPackage) {
            if ($package->getId() === $installedPackage->getId()) {
                continue;
            }

            $installedPackagePath = "{$package->getPath()}/vendor/yiisoft/{$installedPackage->getId()}";
            if (file_exists($installedPackagePath)) {
                // rm dir and replace it with link
                FileHelper::removeDirectory($installedPackagePath);
                symlink($installedPackage->getPath(), $installedPackagePath);
            }
        }
    }

    private function createSymbolicLinks(): void
    {
        $printer = $this->getPrinter();

        $printer
            ->stdoutln('-----------------------------------------------------------------------')
            ->stdoutln('Re-linking vendor directories')
            ->stdoutln('-----------------------------------------------------------------------')
            ->stdoutln();

        $installedPackages = $this->getPackageList()->getInstalledPackages();
        foreach ($installedPackages as $package) {
            $printer
                ->stdout('Package ')
                ->stdout($package->getId(), Color::CYAN)
                ->stdoutln(' linking...');

            $this->linkPackages($package, $installedPackages);
        }

        $printer->stdoutln();
    }
}
