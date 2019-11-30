<?php

namespace Yiisoft\YiiDevTool\Command\Git;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Yiisoft\YiiDevTool\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\Component\Package\Package;

class CommitCommand extends PackageCommand
{
    /** @var string */
    private $message;

    protected function configure()
    {
        $this
            ->setName('git/commit')
            ->addArgument('message', InputArgument::REQUIRED, 'Commit message')
            ->setDescription('Add and commit changes into each package repository');

        $this->addPackageArgument();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->message = $input->getArgument('message');

        foreach ($this->getTargetPackages() as $package) {
            $this->gitCommit($package);
        }

        $this->showPackageErrors();
    }

    private function gitCommit(Package $package): void
    {
        $io = $this->getIO();
        $header = "Committing repository <package>{$package->getId()}</package>";

        $io->header($header);
        $io->writeln("Committing package <package>{$package->getId()}</package>...");

        $process = new Process(['git', 'add', '-A'], $package->getPath());
        $process->run();

        $process = new Process(['git', 'diff-index', '--quiet', 'HEAD'], $package->getPath());
        $process->run();

        if ($process->getExitCode() === 0) {
            $io->warning([
                'Nothing to commit.',
                'Committing skipped.',
            ]);

            return;
        }

        $process = new Process(['git', 'commit', '-m', $this->message], $package->getPath());
        $process->run();

        if ($process->isSuccessful()) {
            $io->write($process->getOutput() . $process->getErrorOutput());
            $io->done();
        } else {
            $output = $process->getErrorOutput();

            $io->writeln($output);
            $io->error([
                "An error occurred during committing package <package>{$package->getId()}</package> repository.",
                'Package committing aborted.',
            ]);

            $package->setError($output, 'committing package repository');
        }
    }
}
