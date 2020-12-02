<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Command\Replicate;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\Component\Console\PackageCommand;

class ReplicateCopyFileCommand extends PackageCommand
{
    private string $source;

    private string $destination;

    protected function configure(): void
    {
        $this
            ->setName('replicate/copy-file')
            ->setDescription('Copy file into each package')
            ->addArgument('source', InputArgument::REQUIRED, 'Source file path')
            ->addArgument('destination', InputArgument::REQUIRED, 'Destination file path')
            ->addPackageArgument()
        ;
    }

    protected function beforeProcessingPackages(InputInterface $input): void
    {
        $this->source = (string)$input->getArgument('source');
        $this->destination = (string)$input->getArgument('destination');

        if (realpath($this->source) === false) {
            throw new \InvalidArgumentException("File \"{$this->source}\" not found.");
        }
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<success>✔ Done</success>';
    }

    protected function processPackage(Package $package): void
    {
        $destination = "{$package->getPath()}/{$this->destination}";

        $io = $this->getIO();
        $io->preparePackageHeader($package, "Copying <file>{$this->source}</file> to <file>{$destination}</file>");

        copy($this->source, $destination);

        $io->done();
    }
}
