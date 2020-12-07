<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Process\Process;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;

final class ExecCommand extends PackageCommand
{
    private string $command;

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

        parent::configure();
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

        $process
            /**
             * Default Process timeout is 60 seconds, but we don't want
             * to limit the duration of command execution, so we reset the timeout.
             */
            ->setTimeout(null)
            /**
             * Keep colors, progress bars and other elements of terminal UI.
             */
            ->setPty(true)
            /**
             * We want to receive the output of the program in real time,
             * so we pass a callback that will read the data as it comes in.
             */
            ->run(function ($type, $data) use ($io) {
                /**
                 * We do not split data into output streams by data type,
                 * because many programs write non-error messages to the error stream.
                 *
                 * We write everything to one regular output stream.
                 */
                $io->important()->write($data);
            });

        /**
         * End each program block with a new line so that
         * the output of different packages does not stick together.
         */
        $io->important()->newLine();
    }
}
