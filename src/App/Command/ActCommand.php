<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Process\Process;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;

#[AsCommand(
    name: 'act',
    description: 'Run GitHub action'
)]
final class ActCommand extends PackageCommand
{
    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, 'Running actions on {package}');

        $actBinary = dirname(__DIR__, 3) . '/bin/act';

        $process = new Process([
            $actBinary,
        ], $package->getPath());
        $process->setTimeout(20);
        $process->run();

        if (!$process->isSuccessful() && $this->actIsNotInstalled($process)) {
            $io
                ->important()
                ->error(<<<ERROR
                    $actBinary is not installed. Use the following to install it:
                    curl --proto '=https' --tlsv1.2 -sSf https://raw.githubusercontent.com/nektos/act/master/install.sh | sudo bash
                ERROR);

            return Command::FAILURE;
        }

        $output = $process->getErrorOutput();
        $this->registerPackageError($package, $output, 'Output:');
        $io
            ->important()
            ->info($process->getOutput() . $output);
    }

    private function actIsNotInstalled(Process $process): bool
    {
        return str_contains($process->getErrorOutput(), 'act: not found');
    }
}
