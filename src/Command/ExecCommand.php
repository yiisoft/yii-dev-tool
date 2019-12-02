<?php

namespace Yiisoft\YiiDevTool\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Process\Process;
use Yiisoft\YiiDevTool\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\Component\Package\Package;

class ExecCommand extends PackageCommand
{
    /** @var string */
    private $command;

    protected function configure()
    {
        $this
            ->setName('exec')
            ->setDescription('Executes the specified console command in each package')
            ->addArgument(
                'console-command',
                InputArgument::REQUIRED,
                <<<DESCRIPTION
Console command to be executed. Complex console commands should be enclosed in quotes.
For example: <fg=green;options=bold>'git commit --message="Feature X" --amend'</>
DESCRIPTION
            );

        $this->addPackageArgument();
    }

    protected function beforeProcessingPackages(InputInterface $input): void
    {
        $this->command = $input->getArgument('console-command');
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<em>Nothing to output</em>';
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, "Executing command in package {package}");

        $process = Process::fromShellCommandline($this->command, $package->getPath());
        $process->setTimeout(null)->run();

        $output = $process->getOutput() . $process->getErrorOutput();

        if (!empty(trim($output))) {
            $io->important()->info($output);
        }
    }
}
