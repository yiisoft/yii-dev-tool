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

final class UpdateCommand extends PackageCommand
{
    public static $defaultName = 'update';
    public static $defaultDescription = 'Pull changes from packages repositories and update composer dependencies';

    private array $additionalComposerUpdateOptions = [];

    private PackageService $packageService;

    public function __construct(PackageService $packageService, string $name = null)
    {
        $this->packageService = $packageService;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->addOption(
            'no-plugins',
            null,
            InputOption::VALUE_NONE,
            'Use <fg=green>--no-plugins</> during <fg=green;options=bold>composer update</>'
        );

        parent::configure();
    }

    protected function beforeProcessingPackages(InputInterface $input): void
    {
        if ($input->getOption('no-plugins') !== false) {
            $this->additionalComposerUpdateOptions[] = '--no-plugins';
        }
    }

    protected function afterProcessingPackages(): void
    {
        $this->packageService->createSymbolicLinks($this->getPackageList(), $this->getIO());
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, 'Updating package {package}');

        if (!$package->isGitRepositoryCloned()) {
            $io->info('Skipped because of package is not installed.');
            return;
        }

        $this->packageService->gitSetUpstream($package, $io);

        $this->packageService->removeSymbolicLinks($package, $this->getPackageList(), $io);

        if ($this->doesPackageContainErrors($package)) {
            return;
        }

        $this->installPackage($package);

        if (!$io->isVerbose()) {
            $io->important()->newLine();
        }
    }

    private function installPackage(Package $package): void
    {
        $io = $this->getIO();

        $this->gitPull($package, $io);

        $this->packageService->composerUpdate(
            $package,
            $this->additionalComposerUpdateOptions,
            $this->getErrorsList(),
            $io
        );
    }

    private function gitPull(Package $package, OutputManager $io): void
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
                'An error occurred during running `git pull`.',
            ]);

            $this->registerPackageError($package, $output, 'running `git pull`');
        }
    }
}
