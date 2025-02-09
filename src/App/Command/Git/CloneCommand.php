<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Git;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\App\PackageService;

#[AsCommand(
    name: 'git/clone',
    description: 'Package repositories cloning',
)]
final class CloneCommand extends PackageCommand
{
    public function __construct(private PackageService $packageService, string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setAliases(['clone']);

        parent::configure();
    }

    protected function beforeProcessingPackages(InputInterface $input): void
    {
        if (!$this->checkSSHConnection()) {
            exit(Command::FAILURE);
        }
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, 'Cloning package {package}');

        $this->packageService->gitClone($package, self::$defaultName, $this->getErrorsList(), $io);

        if ($this->doesPackageContainErrors($package)) {
            return;
        }

        if (!$io->isVerbose()) {
            $io
                ->important()
                ->newLine();
        }
    }
}
