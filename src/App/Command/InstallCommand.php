<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\App\PackageService;

final class InstallCommand extends PackageCommand
{
    protected static $defaultName = 'install';
    protected static $defaultDescription = 'Clone packages repositories and install composer dependencies';

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

    protected function afterProcessingPackages(): void
    {
        $this->packageService->createSymbolicLinks($this->getPackageList(), $this->getIO());
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, 'Installing package {package}');

        if (!$package->isGitRepositoryCloned()) {
            $this->packageService->gitClone($package, self::$defaultName, $this->getErrorsList(), $io);

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
            $io,
        );

        if (!$io->isVerbose()) {
            $io->important()->newLine();
        }
    }
}
