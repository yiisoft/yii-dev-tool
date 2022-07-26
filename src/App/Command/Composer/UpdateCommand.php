<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Composer;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\App\PackageService;

final class UpdateCommand extends PackageCommand
{
    protected static $defaultName = 'composer/update';
    protected static $defaultDescription = 'Update composer dependencies in packages';

    private array $additionalComposerUpdateOptions = [];

    private PackageService $packageService;

    public function __construct(PackageService $packageService, string $name = null)
    {
        $this->packageService = $packageService;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setAliases(['cu'])
            ->addOption(
                'no-plugins',
                null,
                InputOption::VALUE_NONE,
                'Use <fg=green>--no-plugins</> during <fg=green;options=bold>composer update</>'
            )
            ->addOption(
                'ignore-platform-reqs',
                null,
                InputOption::VALUE_NONE,
                'Use <fg=green>--ignore-platform-reqs</> during <fg=green;options=bold>composer update</>'
            );

        parent::configure();
    }

    protected function beforeProcessingPackages(InputInterface $input): void
    {
        if ($input->getOption('no-plugins') !== false) {
            $this->additionalComposerUpdateOptions[] = '--no-plugins';
        }
        if ($input->getOption('ignore-platform-reqs') !== false) {
            $this->additionalComposerUpdateOptions[] = '--ignore-platform-reqs';
        }
    }

    protected function afterProcessingPackages(): void
    {
        $this->packageService->createSymbolicLinks($this->getPackageList(), $this->getIO());
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, 'Updating composer dependencies of package {package}');

        if (!$package->isGitRepositoryCloned()) {
            $io->info('Skipped because of package is not installed.');
            return;
        }

        $this->packageService->gitSetUpstream($package, $io);

        $this->packageService->removeSymbolicLinks($package, $this->getPackageList(), $io);

        if ($this->doesPackageContainErrors($package)) {
            return;
        }

        $this->packageService->composerUpdate(
            $package,
            $this->additionalComposerUpdateOptions,
            $this->getErrorsList(),
            $io
        );

        if (!$io->isVerbose()) {
            $io
                ->important()
                ->newLine();
        }
    }
}
