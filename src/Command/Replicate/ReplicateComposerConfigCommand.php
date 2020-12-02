<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Command\Replicate;

use Yiisoft\YiiDevTool\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\Component\Package\Package;
use Yiisoft\YiiDevTool\Infrastructure\Composer\Config\ComposerConfig;
use Yiisoft\YiiDevTool\Infrastructure\Composer\Config\ComposerConfigMerger;

class ReplicateComposerConfigCommand extends PackageCommand
{
    protected function configure(): void
    {
        $this
            ->setName('replicate/composer-config')
            ->setDescription('Merge <fg=blue;options=bold>config/replicate/composer.json</> into <fg=blue;options=bold>composer.json</> of each package')
            ->addPackageArgument()
        ;
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<success>✔ Done</success>';
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, "Merging <file>config/replicate/composer.json</file> to package {package}");

        $targetPath = "{$package->getPath()}/composer.json";
        if (!file_exists($targetPath)) {
            $io->warning([
                "No <file>composer.json</file> in package {$package->getId()}.",
                "Replication of composer config skipped.",
            ]);

            return;
        }

        $merger = new ComposerConfigMerger();

        try {
            $mergedConfig = $merger->merge(
                ComposerConfig::createByFilePath($targetPath),
                ComposerConfig::createByFilePath($this->getAppRootDir() . 'config/replicate/composer.json'),
            );
        } catch (\Throwable $e) {
            $io->error([
                "An error occurred while working on package \"{$package->getId()}\"",
                $e->getMessage(),
            ]);
            throw $e;
        }

        $mergedConfig->writeToFile($targetPath);

        $io->done();
    }
}
