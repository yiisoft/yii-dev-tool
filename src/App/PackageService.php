<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Yiisoft\Files\FileHelper;
use Yiisoft\YiiDevTool\App\Component\Console\OutputManager;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\App\Component\Package\PackageErrorList;
use Yiisoft\YiiDevTool\App\Component\Package\PackageList;

final class PackageService
{
    public function composerUpdate(
        Package $package,
        array $additionalComposerUpdateOptions,
        PackageErrorList $errorList,
        OutputManager $io
    ): void {
        $io->important()->info('Running `composer update`...');

        if (!file_exists("{$package->getPath()}/composer.json")) {
            $io->warning([
                "No <file>composer.json</file> in package {$package->getName()}.",
                'Running `composer update` skipped.',
            ]);

            return;
        }

        $params = [
            'composer',
            'update',
            '--prefer-dist',
            '--no-progress',
            ...$additionalComposerUpdateOptions,
            '--working-dir',
            $package->getPath(),
            $io->hasColorSupport() ? '--ansi' : '--no-ansi',
        ];

        // Windows doesn't support TTY
        if (DIRECTORY_SEPARATOR === '\\') {
            $params[] = '--no-interaction';
        }

        $process = new Process($params);

        $process->setTimeout(null)->run();

        if ($process->isSuccessful()) {
            $io->info($process->getOutput() . $process->getErrorOutput());
            $io->done();
        } else {
            $output = $process->getErrorOutput();

            $io->important()->info($output);
            $io->error([
                'An error occurred during running `composer update`.',
                'Package update aborted.',
            ]);

            $errorList->set($package, $output, 'running `composer update`');
        }
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

    public function createSymbolicLinks(PackageList $packageList, OutputManager $io): void
    {
        $io->important()->info('Re-linking vendor directories...');

        $installedPackages = $packageList->getInstalledPackages();
        foreach ($installedPackages as $package) {
            $io->info("Package <package>{$package->getId()}</package> linking...");
            $this->linkPackages($package, $installedPackages);
        }

        $io->done();
    }

    public function removeSymbolicLinks(Package $package, PackageList $packageList, OutputManager $io): void
    {
        $vendorDirectory = "{$package->getPath()}/vendor";
        if (!is_dir($vendorDirectory)) {
            return;
        }

        $io->important()->info('Removing old package symlinks...');

        $installedPackages = $packageList->getInstalledPackages();
        foreach ($installedPackages as $installedPackage) {
            $packagePath = "{$vendorDirectory}/{$installedPackage->getName()}";

            if (is_dir($packagePath) && is_link($packagePath)) {
                $io->info("Removing symlink <file>{$packagePath}</file>");
                FileHelper::unlink($packagePath);
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

            $installedPackagePath = "{$vendorDirectory}/{$installedPackage->getName()}";
            if (is_dir($installedPackagePath)) {
                $fs->remove($installedPackagePath);

                $originalPath = DIRECTORY_SEPARATOR === '\\' ?
                    $installedPackage->getPath() :
                    "../../../{$installedPackage->getId()}";
                $fs->symlink($originalPath, $installedPackagePath);
            }
        }
    }
}
