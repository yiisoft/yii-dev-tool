<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command;

use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;

final class ListPackagesCommand extends PackageCommand
{
    private string $command;

    protected function configure()
    {
        $this
            ->setName('list-packages')
            ->setDescription('List all packages');

        parent::configure();
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<em>No packages installed</em>';
    }

    protected function processPackage(Package $package): void
    {
        $url = $package->enabled() ? $package->getConfiguredRepositoryUrl() : '-';
        $info = sprintf('%' . $this->getMaxNameLength() . 's %s', $package->getId(), $url);
        $this->info($info);
    }

    private function info(string $message): void
    {
        $io = $this->getIO();
        $io->important()->info($message);
    }

    private function getMaxNameLength(): int
    {
        $installedPackages = $this->getPackageList()->getInstalledPackages();
        $maxLength = 0;

        foreach ($installedPackages as $package) {
            $length = strlen($package->getId());

            if ($length > $maxLength) {
                $maxLength = $length;
            }
        }

        return $maxLength;
    }
}
