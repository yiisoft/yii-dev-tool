<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\App\PackageService;

final class InstallCommand extends PackageCommand
{
    public static $defaultName = 'install';
    public static $defaultDescription = 'Clone packages repositories and install composer dependencies';

    private array $additionalComposerInstallOptions = [];

    private PackageService $packageService;

    public function __construct(PackageService $packageService, string $name = null)
    {
        $this->packageService = $packageService;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setAliases(['i'])
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
                'Package install aborted.',
            ]);

            $this->registerPackageError($package, $output, 'cloning package repository');
        }
    }

    protected function afterProcessingPackages(): void
    {
        $this->packageService->createSymbolicLinks($this->getPackageList(), $this->getIO());
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, 'Installing package {package}');

        if (!$package->isGitRepositoryCloned()) {
            $this->gitClone($package);

            if ($this->doesPackageContainErrors($package)) {
                return;
            }
        }

        $this->packageService->gitSetUpstream($package, $io);

        $this->packageService->removeSymbolicLinks($package, $this->getPackageList(), $io);

        if ($this->doesPackageContainErrors($package)) {
            return;
        }

        $this->packageService->composerInstall(
            $package,
            $this->additionalComposerInstallOptions,
            $this->getErrorsList(),
            $this->getIO()
        );

        if (!$io->isVerbose()) {
            $io->important()->newLine();
        }
    }
}
