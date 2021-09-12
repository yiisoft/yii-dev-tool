<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Composer;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\App\PackageService;

final class UpdateCommand extends PackageCommand
{
    public static $defaultName = 'composer/update';
    public static $defaultDescription = 'Update composer dependencies in packages';

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
        $io->preparePackageHeader($package, 'Updating composer dependencies of package {package}');

        if (!$package->isGitRepositoryCloned()) {
            $io->info('Skipped because of package is not installed.');
            return;
        }

        $this->packageService->setGitUpstream($package, $io);

        $this->packageService->removeSymbolicLinks($package, $this->getPackageList(), $io);

        if ($this->doesPackageContainErrors($package)) {
            return;
        }

        $this->updatePackage($package);

        if (!$io->isVerbose()) {
            $io->important()->newLine();
        }
    }

    private function updatePackage(Package $package): void
    {
        $io = $this->getIO();

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
            ...$this->additionalComposerUpdateOptions,
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

            $this->registerPackageError($package, $output, 'running `composer update`');
        }
    }
}
