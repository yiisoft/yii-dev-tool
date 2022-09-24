<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App;

use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Yiisoft\Files\FileHelper;
use Yiisoft\YiiDevTool\App\Component\Console\OutputManager;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\App\Component\Package\PackageErrorList;
use Yiisoft\YiiDevTool\App\Component\Package\PackageList;
use Yiisoft\YiiDevTool\Infrastructure\Composer\ComposerPackage;

final class PackageService
{
    public function composerInstall(
        Package $package,
        array $additionalOptions,
        PackageErrorList $errorList,
        OutputManager $io
    ): void {
        $this->composerInstallOrUpdate(
            'install',
            $package,
            $additionalOptions,
            $errorList,
            $io
        );
    }

    public function composerUpdate(
        Package $package,
        array $additionalOptions,
        PackageErrorList $errorList,
        OutputManager $io
    ): void {
        $this->composerInstallOrUpdate(
            'update',
            $package,
            $additionalOptions,
            $errorList,
            $io
        );
    }

    public function gitClone(
        Package $package,
        string $commandName,
        PackageErrorList $errorList,
        OutputManager $io
    ): void {
        $io
            ->important()
            ->info('Cloning package repository...');

        if ($package->isGitRepositoryCloned()) {
            $io->warning([
                'The package already contains <file>.git</file> directory.',
                'Cloning skipped.',
            ]);

            return;
        }

        $io->info("Repository url: <file>{$package->getConfiguredRepositoryUrl()}</file>");

        $process = new Process(['git', 'clone', $package->getConfiguredRepositoryUrl(), $package->getPath()]);
        $process
            ->setTimeout(null)
            ->run();

        if ($process->isSuccessful()) {
            $io->info($process->getOutput() . $process->getErrorOutput());
            $io->done();
            return;
        }

        $output = $process->getErrorOutput();
        $io
            ->important()
            ->info($output);

        $io->error([
            "An error occurred during cloning package <package>{$package->getName()}</package> repository.",
            "Package $commandName aborted.",
        ]);

        $errorList->set($package, $output, 'cloning package repository');
    }

    public function gitSetUpstream(Package $package, OutputManager $io): void
    {
        if ($package->isConfiguredRepositoryPersonal()) {
            $gitWorkingCopy = $package->getGitWorkingCopy();
            $remoteName = 'upstream';

            if (!$gitWorkingCopy->hasRemote($remoteName)) {
                $upstreamUrl = $package->getOriginalRepositoryHttpsUrl();
                $io->info("Setting repository remote 'upstream' to <file>$upstreamUrl</file>");
                $gitWorkingCopy->addRemote($remoteName, $upstreamUrl);
                $io->done();
            }
        }
    }

    public function createSymbolicLinks(Package $package, PackageList $packageList, OutputManager $io): void
    {
        $io
            ->important()
            ->info('Re-linking vendor directories for package: ' . $package->getName());

        $installedPackages = $packageList->getInstalledAndEnabledPackages();
        try {
            $this->linkPackages($package, $installedPackages);
        } catch (RuntimeException $e) {
            $io
                ->important()
                ->error("Linking error package {$package->getName()}: " . $e->getMessage());
        }

        $io->done();
    }

    public function removeSymbolicLinks(Package $package, PackageList $packageList, OutputManager $io): void
    {
        $vendorDirectory = "{$package->getPath()}/vendor";
        if (!is_dir($vendorDirectory)) {
            return;
        }

        $io
            ->important()
            ->info('Removing old package symlinks...');

        $installedPackages = $packageList->getInstalledPackages();
        foreach ($installedPackages as $installedPackage) {
            try {
                $composerPackage = new ComposerPackage($installedPackage->getName(), $installedPackage->getPath());
                $upstreamNamePackage = $composerPackage
                                                        ->getComposerConfig()
                                                        ->getSection('name');
                $packagePath = "{$vendorDirectory}/{$upstreamNamePackage}";

                if (is_dir($packagePath) && is_link($packagePath)) {
                    $io->info("Removing symlink <file>{$packagePath}</file>");
                    FileHelper::unlink($packagePath);
                }
            } catch (RuntimeException $e) {
                $io
                    ->important()
                    ->error("Error while removing package links {$installedPackage->getName()}: " . $e->getMessage());
            }
        }

        $io->done();
    }

    /**
     * @param Package[] $installedPackages
     */
    private function linkPackages(Package $package, array $installedPackages): void
    {
        $vendorDirectory = "{$package->getPath()}/vendor";
        if (!is_dir($vendorDirectory)) {
            return;
        }

        $fs = new Filesystem();
        foreach ($installedPackages as $installedPackage) {
            if ($package->getName() === $installedPackage->getName()) {
                continue;
            }

            $composerPackage = new ComposerPackage($installedPackage->getName(), $installedPackage->getPath());
            $upstreamNamePackage = $composerPackage
                                                    ->getComposerConfig()
                                                    ->getSection('name');
            $installedPackagePath = "{$vendorDirectory}/{$upstreamNamePackage}";
            if (is_dir($installedPackagePath)) {
                $fs->remove($installedPackagePath);

                $originalPath = DIRECTORY_SEPARATOR === '\\' ?
                    $installedPackage->getPath() :
                    "../../../{$installedPackage->getId()}";
                $fs->symlink($originalPath, $installedPackagePath);
            }
        }
    }

    private function composerInstallOrUpdate(
        string $command,
        Package $package,
        array $additionalOptions,
        PackageErrorList $errorList,
        OutputManager $io
    ): void {
        $io
            ->important()
            ->info("Running `composer $command`...");

        if (!file_exists("{$package->getPath()}/composer.json")) {
            $io->warning([
                "No <file>composer.json</file> in package {$package->getName()}.",
                "Running `composer $command` skipped.",
            ]);

            return;
        }

        $params = [
            'composer',
            $command,
            '--prefer-dist',
            '--no-progress',
            ...$additionalOptions,
            '--working-dir',
            $package->getPath(),
            $io->hasColorSupport() ? '--ansi' : '--no-ansi',
        ];

        // Windows doesn't support TTY
        if (DIRECTORY_SEPARATOR === '\\') {
            $params[] = '--no-interaction';
        }

        $process = new Process($params);

        $process
            ->setTimeout(null)
            ->run();

        if ($process->isSuccessful()) {
            $io->info($process->getOutput() . $process->getErrorOutput());
            $io->done();
        } else {
            $output = $process->getErrorOutput();

            $io
                ->important()
                ->info($output);
            $io->error([
                "An error occurred during running `composer $command`.",
                "Package $command aborted.",
            ]);

            $errorList->set($package, $output, "running `composer $command`");
        }
    }
}
