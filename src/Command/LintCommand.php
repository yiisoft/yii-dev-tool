<?php

namespace Yiisoft\YiiDevTool\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Yiisoft\YiiDevTool\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\Component\Package\Package;

class LintCommand extends PackageCommand
{
    protected function configure()
    {
        $this
            ->setName('lint')
            ->setDescription('Check packages according to PSR12 standard');

        $this->addPackageArgument();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        foreach ($this->getTargetPackages() as $package) {
            $this->lint($package);
        }
    }

    private function lint(Package $package): void
    {
        $io = $this->getIO();
        $header = "Linting package <package>{$package->getId()}</package>";

        $io->header($header);

        $process = new Process([
            './vendor/bin/phpcs',
            $package->getPath(),
            $io->hasColorSupport() ? '--colors' : '--no-colors',
            '--standard=PSR12',
            '--ignore=*/vendor/*,*/docs/*',
         ], __DIR__ . '/../../');

        $process->run();

        if ($process->getExitCode() > 0) {
            $io->writeln($process->getOutput() . $process->getErrorOutput());
        } else {
            $io->success('✔ No problems found.');
        }
    }
}
