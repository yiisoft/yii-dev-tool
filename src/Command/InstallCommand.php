<?php

namespace Yiisoft\YiiDevTool\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Process;
use Yiisoft\YiiDevTool\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\Component\Package\Package;

class InstallCommand extends PackageCommand
{
    /** @var bool */
    private $updateMode = false;

    public function useUpdateMode(): self
    {
        $this->updateMode = true;

        return $this;
    }

    protected function configure()
    {
        $this
            ->setName('install')
            ->setDescription('Install packages');

        $this->addPackageArgument();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        foreach ($this->getTargetPackages() as $package) {
            $this->install($package);
        }

        $this->createSymbolicLinks();
        $this->showPackageErrors();
    }

    private function gitClone(Package $package): void
    {
        $io = $this->getIO();

        $io->writeln("Cloning package <package>{$package->getId()}</package> repository...");

        if ($package->isGitRepositoryCloned()) {
            $io->warning([
                'The package already contains <file>.git</file> directory.',
                'Cloning skipped.',
            ]);

            return;
        }

        $io->writeln("Repository url: <file>{$package->getRepositoryUrl()}</file>");

        $process = new Process(['git', 'clone', $package->getRepositoryUrl(), $package->getPath()]);
        $process->setTimeout(null)->run();

        if ($process->isSuccessful()) {
            $io->write($process->getOutput() . $process->getErrorOutput());
            $io->done();
        } else {
            $output = $process->getErrorOutput();

            $io->writeln($output);
            $io->error([
                "An error occurred during cloning package <package>{$package->getId()}</package> repository.",
                'Package ' . ($this->updateMode ? 'update' : 'install') . ' aborted.',
            ]);

            $package->setError($output, 'cloning package repository');
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

        $io->writeln('Removing old package symlinks...');

        /** @var SplFileInfo $fileInfo */
        foreach ($finder->directories()->in($vendorYiisoftDirectory) as $fileInfo) {
            $directoryPath = $fileInfo->getPathname();

            if (is_link($directoryPath)) {
                $io->writeln("Removing symlink <file>$directoryPath</file>");
                $fs->remove($directoryPath);
            }
        }

        $io->done();
    }

    private function composerInstall(Package $package): void
    {
        $io = $this->getIO();

        $composerCommandName = $this->updateMode ? 'update' : 'install';

        $io->writeln("Running `composer $composerCommandName` in package <package>{$package->getId()}</package>...");

        if (!file_exists("{$package->getPath()}/composer.json")) {
            $io->warning([
                "No <file>composer.json</file> in package {$package->getId()}.",
                "Running `composer $composerCommandName` skipped.",
            ]);

            return;
        }

        $process = new Process([
            'composer',
            $composerCommandName,
            '--prefer-dist',
            '--no-progress',
            '--working-dir',
            $package->getPath(),
            $io->hasColorSupport() ? '--ansi' : '--no-ansi',
        ]);

        $process->setTimeout(null)->run();

        if ($process->isSuccessful()) {
            $io->write($process->getOutput() . $process->getErrorOutput());
            $io->done();
        } else {
            $output = $process->getErrorOutput();

            $io->writeln($output);
            $io->error([
                "An error occurred during running `composer $composerCommandName`.",
                'Package ' . ($this->updateMode ? 'update' : 'install') . ' aborted.',
            ]);

            $package->setError($output, "running `composer $composerCommandName`");
        }
    }

    private function install(Package $package): void
    {
        $io = $this->getIO();
        $header = ($this->updateMode ? 'Updating' : 'Installing') . " package <package>{$package->getId()}</package>";

        if ($package->disabled()) {
            if ($this->areTargetPackagesSpecifiedExplicitly()) {
                $io->header($header);
                $io->warning([
                    "Package <package>{$package->getId()}</package> disabled by configuration.",
                    'Skipped.',
                ]);
            }

            return;
        }

        $io->header($header);

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
                $fs = new Filesystem();
                $fs->remove($installedPackagePath);
                $fs->symlink($installedPackage->getPath(), $installedPackagePath);
            }
        }
    }

    private function createSymbolicLinks(): void
    {
        $io = $this->getIO();

        $io->header('Re-linking vendor directories');

        $installedPackages = $this->getPackageList()->getInstalledPackages();
        foreach ($installedPackages as $package) {
            $io->writeln("Package <package>{$package->getId()}</package> linking...");
            $this->linkPackages($package, $installedPackages);
        }

        $io->done();
    }
}
