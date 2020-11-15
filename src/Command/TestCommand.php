<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Command;

use Symfony\Component\Process\Process;
use Yiisoft\YiiDevTool\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\Component\Package\Package;

final class TestCommand extends PackageCommand
{
    protected function configure(): void
    {
        $this
            ->setName('test')
            ->setDescription('Test packages')
            ->addPackageArgument()
        ;
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
            $process = new Process([
                './vendor/bin/phpunit',
                '--colors',
                '--no-interaction',
            ], $package->getPath());
            $process->setTimeout(20);
            $process->run();
        }

        if ($process->getExitCode() === 0) {
            $io->success('✔ All tests were passed successfully.');

            return;
        }

        $output = $process->getErrorOutput();
        $package->setError($output, 'testing package');
        $io->important()->info($process->getOutput() . $output);
    }

    private function isComposerTestNotImplemented(Process $process): bool
    {
        return strpos($process->getErrorOutput(), 'Command "test" is not defined') !== false;
    }
}
