<?php

namespace Yiisoft\YiiDevTool\Command\Replicate;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\YiiDevTool\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\Component\Package\Package;

class ReplicateComposerConfigCommand extends PackageCommand
{
    protected function configure()
    {
        $this
            ->setName('replicate/composer-config')
            ->setDescription('Merge <fg=blue;options=bold>config/replicate/composer.json</> into <fg=blue;options=bold>composer.json</> of each package');

        $this->addPackageArgument();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        foreach ($this->getTargetPackages() as $package) {
            $this->replicateToPackage($package);
        }

        $this->showPackageErrors();
    }

    private function replicateToPackage(Package $package): void
    {
        $io = $this->getIO();

        $header = "Merging <file>config/replicate/composer.json</file> to package <package>{$package->getId()}</package>";
        $io->header($header);

        $targetPath = "{$package->getPath()}/composer.json";
        if (!file_exists($targetPath)) {
            $io->warning([
                "No <file>composer.json</file> in package {$package->getId()}.",
                "Replication of composer config skipped.",
            ]);

            return;
        }

        $currentContent = file_get_contents($targetPath);
        $currentConfig = json_decode($currentContent, true);

        $replicationSourceContent = file_get_contents(__DIR__ . '/../../../config/replicate/composer.json');
        $replicationSourceConfig = json_decode($replicationSourceContent, true);

        $mergedConfig = array_merge($currentConfig, $replicationSourceConfig);
        $mergedContent = json_encode($mergedConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($targetPath, $mergedContent);

        $io->done();
    }
}
