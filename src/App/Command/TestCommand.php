<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;

final class TestCommand extends PackageCommand
{
    protected static $defaultName = 'test';
    protected static $defaultDescription = 'Test packages';

    private ?string $filter;

    protected function configure(): void
    {
        $this
            ->setAliases(['t'])
            ->addOption('filter', null, InputOption::VALUE_REQUIRED, 'Filter test cases by the word.');

        parent::configure();
    }

    protected function beforeProcessingPackages(InputInterface $input): void
    {
        $filter = $input->getOption('filter');
        $this->filter = $filter === null ? null : (string) $filter;
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, 'Testing package {package}');

        $process = new Process([
            'composer',
            'test',
        ], $package->getPath());
        $process->setTimeout(20);
        $process->run();

        if (!$process->isSuccessful() && $this->isComposerTestNotImplemented($process)) {
            $command = [
                './vendor/bin/phpunit',
                '--colors',
                '--no-interaction',
            ];

            if ($this->filter !== null) {
                $command[] = '--filter';
                $command[] = $this->filter;
            }

            $process = new Process($command, $package->getPath());
            $process->setTimeout(20);
            $process->run();
        }

        if ($process->getExitCode() === 0) {
            $io->success('✔ All tests were passed successfully.');

            return;
        }

        if ($process->getExitCode() === 127) {
            $io->info('No testing engine found.');

            return;
        }

        $output = $process->getErrorOutput();
        $this->registerPackageError($package, $output, 'testing package');
        $io
            ->important()
            ->info($process->getOutput() . $output);
    }

    private function isComposerTestNotImplemented(Process $process): bool
    {
        return strpos($process->getErrorOutput(), 'Command "test" is not defined') !== false;
    }
}
