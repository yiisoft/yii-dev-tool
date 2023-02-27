<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command;

use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\App\Component\PhpStorm\Folders;
use Symfony\Component\Console\Input\InputInterface;

final class IdeCommand extends PackageCommand
{
    protected static $defaultName = 'ide';
    protected static $defaultDescription = 'Adjust PhpStorm configs';

    private Folders $folders;

    protected function beforeProcessingPackages(InputInterface $input): void
    {
        $this->folders = new Folders(dirname(__DIR__, 3) . '/.idea');
    }

    protected function processPackage(Package $package): void
    {
        $path = 'dev/' . $package->getId();

        if (is_dir($package->getPath() . '/vendor/yiisoft')) {
            $this->folders->excludeFolder($path . '/vendor/yiisoft');
        }

        if (is_dir($package->getPath() . '/tests')) {
            $this->folders->testsFolder($path . '/tests');
        }

        if (is_dir($package->getPath() . '/src')) {
            $this->folders->sourceFolder($path . '/src');
        }
    }

    protected function afterProcessingPackages(InputInterface $input): void
    {
        $this->folders->write();
    }
}
