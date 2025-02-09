<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Git;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Process\Process;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;

#[AsCommand(
    name: 'git/commit',
    description: 'Add and commit changes into each package repository'
)]
final class CommitCommand extends PackageCommand
{
    private string $message;

    protected function configure()
    {
        $this
            ->setAliases(['commit'])
            ->addArgument('message', InputArgument::REQUIRED, 'Commit message')
        ;

        parent::configure();
    }

    protected function beforeProcessingPackages(InputInterface $input): void
    {
        $this->message = $input->getArgument('message');
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<em>Nothing to commit</em>';
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, 'Committing {package} repository');

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
            $io
                ->important()
                ->info($process->getOutput() . $process->getErrorOutput());
            $io->done();
        } else {
            $output = $process->getErrorOutput();

            $io
                ->important()
                ->info($output);
            $io->error([
                "An error occurred during committing package <package>{$package->getId()}</package> repository.",
                'Package committing aborted.',
            ]);

            $this->registerPackageError($package, $output, 'committing package repository');
        }
    }
}
