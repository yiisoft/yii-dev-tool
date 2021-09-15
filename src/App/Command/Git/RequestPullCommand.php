<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Git;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;

final class RequestPullCommand extends PackageCommand
{
    protected static $defaultName = 'git/pr/create';
    protected static $defaultDescription = 'Create a GitHub pull request';

    private string $title;

    private string $body;

    private bool $isDraft = true;

    protected function configure(): void
    {
        $this
            ->setAliases(['pr'])
            ->addArgument('title', InputArgument::REQUIRED, 'Title of a pull request.')
            ->addOption('body', 'b', InputOption::VALUE_REQUIRED, 'Description of a pull request.')
            ->addOption('no-draft', null, InputOption::VALUE_NONE, 'Make a non-draft pull request.')
        ;

        parent::configure();
    }

    protected function beforeProcessingPackages(InputInterface $input): void
    {
        $this->title = trim((string) $input->getArgument('title'));
        $this->body = trim((string) $input->getOption('body'));
        $this->isDraft = !(bool) $input->getOption('no-draft');
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<em>PR was not created</em>';
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, "Creating PR for {package} repository");

        $processParameters = [
            'gh',
            'pr',
            'create',
            '--title',
            $this->title,
        ];

        if (!empty($this->body)) {
            $processParameters[] = '--body';
            $processParameters[] = $this->body;
        }

        if ($this->isDraft) {
            $processParameters[] = '--draft';
        }

        $process = new Process($processParameters, $package->getPath());
        $process->run();

        if ($process->isSuccessful()) {
            $io->important()->info($process->getOutput() . $process->getErrorOutput());
            $io->done();

            return;
        }

        if ($process->getExitCode() === 127) {
            $io->error([
                'It seems that "gh" is not installed.',
                'Please visit https://cli.github.com/ and install that package to use this command.',
            ]);

            return;
        }

        $output = $process->getErrorOutput();

        $io->important()->info($output);
        $io->error([
            "An error occurred during creating PR for package <package>{$package->getId()}</package> repository.",
            'Creating PR aborted.',
        ]);

        $this->registerPackageError($package, $output, 'creating PR');
    }
}
