<?php

namespace Yiisoft\YiiDevTool\Command\Replicate;

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

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<success>✔ Done</success>';
    }

    // TODO: Write tests
    private function merge($a, $b): array
    {
        foreach ($b as $key => $value) {
            if (is_string($key)) {
                if (array_key_exists($key, $a) && is_array($value)) {
                    $a[$key] = $this->merge($a[$key], $value);
                } else {
                    $a[$key] = $value;
                }
            } else {
                $index = array_search($value, $a, true);

                if ($index === false) {
                    $a[] = $value;
                } else {
                    $a[$index] = $value;
                }
            }
        }

        return $a;
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

        $currentContent = file_get_contents($targetPath);
        $currentConfig = json_decode($currentContent, true);

        $replicationSourceContent = file_get_contents(__DIR__ . '/../../../config/replicate/composer.json');
        $replicationSourceConfig = json_decode($replicationSourceContent, true);

        $mergedConfig = $this->merge($currentConfig, $replicationSourceConfig);
        $mergedContent = json_encode($mergedConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($targetPath, $mergedContent);

        $io->done();
    }
}
