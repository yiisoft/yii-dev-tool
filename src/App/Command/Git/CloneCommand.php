<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Git;

use Symfony\Component\Console\Input\InputInterface;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\App\PackageService;

final class CloneCommand extends PackageCommand
{
    protected static $defaultName = 'git/clone';
    protected static $defaultDescription = 'Package repositories cloning';

    private PackageService $packageService;

    public function __construct(PackageService $packageService, string $name = null)
    {
        $this->packageService = $packageService;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setAliases(['clone']);

        parent::configure();
    }

    protected function beforeProcessingPackages(InputInterface $input): void
    {
        $this->checkSSHConnection();
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
