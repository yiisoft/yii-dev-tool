<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Process;
use Yiisoft\YiiDevTool\App\Component\Console\OutputManager;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;

final class InstallCommand extends PackageCommand
{
    private bool $updateMode = false;
    private array $additionalComposerInstallOptions = [];
    private string $composerCommandName = 'install';

    public function useUpdateMode(): self
    {
        $this->updateMode = true;

        return $this;
    }

    protected function configure()
    {
        $this
            ->setName('install')
            ->setDescription('Install packages')
            ->addOption(
                'no-plugins',
                null,
                InputOption::VALUE_NONE,
                'Use <fg=green>--no-plugins</> during <fg=green;options=bold>composer install</>'
            );

        parent::configure();
    }

    protected function beforeProcessingPackages(InputInterface $input): void
    {
        if ($input->getOption('no-plugins') !== false) {
            $this->additionalComposerInstallOptions[] = '--no-plugins';
        }
    }

    protected function afterProcessingPackages(): void
    {
        $this->createSymbolicLinks();
    }

    private function gitClone(Package $package): void
    {
        $io = $this->getIO();
        $io->important()->info("Cloning package repository...");

        if ($package->isGitRepositoryCloned()) {
            $io->warning([
                'The package already contains <file>.git</file> directory.',
                'Cloning skipped.',
            ]);

            return;
        }

        $io->info("Repository url: <file>{$package->getConfiguredRepositoryUrl()}</file>");

        $process = new Process(['git', 'clone', $package->getConfiguredRepositoryUrl(), $package->getPath()]);
        $process->setTimeout(null)->run();

        if ($process->isSuccessful()) {
            $io->info($process->getOutput() . $process->getErrorOutput());
            $io->done();
        } else {
            $output = $process->getErrorOutput();

            $io->important()->info($output);
            $io->error([
                "An error occurred during cloning package <package>{$package->getId()}</package> repository.",
                "Package {$this->composerCommandName} aborted.",
            ]);

            $this->registerPackageError($package, $output, 'cloning package repository');
        }
    }

    private function setUpstream(Package $package): void
    {
        $io = $this->getIO();

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

    private function removeSymbolicLinks(Package $package): void
    {
        $vendorYiisoftDirectory = "{$package->getPath()}/vendor/yiisoft";
        if (!file_exists($vendorYiisoftDirectory)) {
            return;
        }

        $finder = new Finder();
        $fs = new Filesystem();
        $io = $this->getIO();

        $io->important()->info('Removing old package symlinks...');

        /** @var SplFileInfo $fileInfo */
        foreach ($finder->directories()->in($vendorYiisoftDirectory) as $fileInfo) {
            $directoryPath = $fileInfo->getPathname();

            if (is_link($directoryPath)) {
                $io->info("Removing symlink <file>$directoryPath</file>");
                $fs->remove($directoryPath);
            }
        }

        $io->done();
    }

    private function installPackage(Package $package): void
    {
        $io = $this->getIO();

        $io->important()->info("Running `composer {$this->composerCommandName}`...");

        if (!file_exists("{$package->getPath()}/composer.json")) {
            $io->warning([
                "No <file>composer.json</file> in package {$package->getId()}.",
                "Running `composer {$this->composerCommandName}` skipped.",
            ]);

            return;
        }
        if ($this->updateMode) {
            $this->gitPull($io, $package);
        }

        $this->composerInstall($package, $io);
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, ($this->updateMode ? 'Updating' : 'Installing') . " package {package}");

        $hasGitRepositoryAlreadyBeenCloned = $package->isGitRepositoryCloned();

        if ($this->updateMode && !$hasGitRepositoryAlreadyBeenCloned) {
            $io->info("Skipped because of package is not installed.");
            return;
        }
        if (!$this->updateMode || !$hasGitRepositoryAlreadyBeenCloned) {
            $this->gitClone($package);

            if ($this->doesPackageContainErrors($package)) {
                return;
            }
        }
        $this->composerCommandName = $this->updateMode ? 'update' : 'install';

        $this->setUpstream($package);

        if ($hasGitRepositoryAlreadyBeenCloned) {
            $this->removeSymbolicLinks($package);

            if ($this->doesPackageContainErrors($package)) {
                return;
            }
        }

        $this->installPackage($package);

        if (!$io->isVerbose()) {
            $io->important()->newLine();
        }
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
                $fs = new Filesystem();
                $fs->remove($installedPackagePath);

                $originalPath = DIRECTORY_SEPARATOR === '\\' ?
                    $installedPackage->getPath() :
                    "../../../{$installedPackage->getId()}";
                $fs->symlink($originalPath, $installedPackagePath);
            }
        }
    }

    private function createSymbolicLinks(): void
    {
        $io = $this->getIO();

        $io->important()->info('Re-linking vendor directories...');

        $installedPackages = $this->getPackageList()->getInstalledPackages();
        foreach ($installedPackages as $package) {
            $io->info("Package <package>{$package->getId()}</package> linking...");
            $this->linkPackages($package, $installedPackages);
        }

        $io->done();
    }

    private function gitPull(OutputManager $io, Package $package): void
    {
        $io->important()->info('Pulling repository');
        $process = new Process([
            'git',
            'pull',
        ]);
        $process->setWorkingDirectory($package->getPath());

        $process->setTimeout(null)->run();
        if ($process->isSuccessful()) {
            $io->info($process->getOutput() . $process->getErrorOutput());
            $io->done();
        } else {
            $output = $process->getErrorOutput();

            $io->important()->info($output);
            $io->error([
                "An error occurred during running `git pull`.",
            ]);

            $this->registerPackageError($package, $output, "running `git pull`");
        }
    }

    private function composerInstall(Package $package, OutputManager $io): void
    {
        $params = [
            'composer',
            $this->composerCommandName,
            '--prefer-dist',
            '--no-progress',
            ...$this->additionalComposerInstallOptions,
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
                "An error occurred during running `composer {$this->composerCommandName}`.",
                "Package {$this->composerCommandName} aborted.",
            ]);

            $this->registerPackageError($package, $output, "running `composer {$this->composerCommandName}`");
        }
    }
}
