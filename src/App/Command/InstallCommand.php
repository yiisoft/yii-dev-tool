<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\App\PackageService;

#[AsCommand(
    name: 'install',
    description: 'Clone packages repositories and install composer dependencies'
)]
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
            ->setAliases(['i'])
            ->addOption(
                'no-plugins',
                null,
                InputOption::VALUE_NONE,
                'Use <fg=green>--no-plugins</> during <fg=green;options=bold>composer install</>'
            )
            ->addOption(
                'no-symlinks',
                null,
                InputOption::VALUE_OPTIONAL,
                'Do not create symbolic links after process',
                false
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

    protected function afterProcessingPackages(InputInterface $input): void
    {
        if ($input->getOption('no-symlinks') === false) {
            $this->packageService->createSymbolicLinks($this->getPackageList(), $this->getIO());
        }
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
