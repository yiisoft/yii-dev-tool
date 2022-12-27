<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Packages;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\App\PackageService;

final class InstallCommand extends PackageCommand
{
    private array $additionalComposerInstallOptions = [];

    public function __construct(private PackageService $packageService, string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setName('packages/install')
            ->setAliases(['i'])
            ->setDescription('Clone packages repositories and install composer dependencies')
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
        if (!$this->checkSSHConnection()) {
            exit(Command::FAILURE);
        }

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
            $this->packageService->gitClone($package, $this->getName(), $this->getErrorsList(), $io);

            if ($this->doesPackageContainErrors($package)) {
                return;
            }
        }

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
            $io
                ->important()
                ->newLine();
        }
    }
}
