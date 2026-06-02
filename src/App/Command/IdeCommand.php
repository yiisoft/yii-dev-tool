<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\App\Component\PhpStorm\Folders;
use Symfony\Component\Console\Input\InputInterface;

#[AsCommand(
    name: 'ide',
    description: 'Adjust PhpStorm configs',
)]
final class IdeCommand extends PackageCommand
{
    private Folders $folders;

    protected function beforeProcessingPackages(InputInterface $input): void
    {
        $this->folders = new Folders(dirname(__DIR__, 3) . '/.idea');
    }

    protected function processPackage(Package $package): void
    {
        $rootPackage = $package->getRootPackage();
        if ($rootPackage === null) {
            $path = 'dev/' . $package->getId();
        } else {
            $path = 'dev/' . $rootPackage->getId() . '/' . $package->getId();
        }

        $this->folders->sourceFolder($path . '/src');
        $this->folders->testsFolder($path . '/tests');
        $this->folders->excludeFolder($path . '/vendor/yiisoft');
    }

    protected function afterProcessingPackages(InputInterface $input): void
    {
        $this->folders->write();
    }
}
