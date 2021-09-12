<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;
use Yiisoft\YiiDevTool\App\Component\Console\OutputManager;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\App\PackageService;

final class InstallCommand extends PackageCommand
{
    private bool $updateMode = false;
    private array $additionalComposerInstallOptions = [];
    private string $composerCommandName = 'install';

    private PackageService $packageService;

    public function __construct(PackageService $packageService, string $name = null)
    {
        $this->packageService = $packageService;
        parent::__construct($name);
    }

    public function useUpdateMode(): self
    {
        $this->updateMode = true;

        return $this;
    }

    protected function configure(): void
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
                "An error occurred during cloning package <package>{$package->getName()}</package> repository.",
                'Package {$this->composerCommandName} aborted.',
            ]);

            $this->registerPackageError($package, $output, 'cloning package repository');
        }
    }

    private function installPackage(Package $package): void
    {
        $io = $this->getIO();

        $io->important()->info("Running `composer {$this->composerCommandName}`...");

        if (!file_exists("{$package->getPath()}/composer.json")) {
            $io->warning([
                "No <file>composer.json</file> in package {$package->getName()}.",
                "Running `composer {$this->composerCommandName}` skipped.",
            ]);

            return;
        }
        if ($this->updateMode) {
            $this->gitPull($io, $package);
        }

        $this->composerInstall($package, $io);
    }

    protected function afterProcessingPackages(): void
    {
        $this->packageService->createSymbolicLinks($this->getPackageList(), $this->getIO());
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

        $this->packageService->gitSetUpstream($package, $io);

        if ($hasGitRepositoryAlreadyBeenCloned) {
            $this->packageService->removeSymbolicLinks($package, $this->getPackageList(), $io);

            if ($this->doesPackageContainErrors($package)) {
                return;
            }
        }

        $this->installPackage($package);

        if (!$io->isVerbose()) {
            $io->important()->newLine();
        }
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
