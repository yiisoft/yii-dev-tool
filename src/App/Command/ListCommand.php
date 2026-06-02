<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;

#[AsCommand(
    name: 'list',
    description: 'List all packages'
)]
final class ListCommand extends PackageCommand
{
    protected function configure()
    {
        $this->setAliases(['l']);

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
        $io
            ->important()
            ->info($message);
    }

    private function getMaxNameLength(): int
    {
        $installedPackages = $this
            ->getPackageList()
            ->getInstalledAndEnabledPackages();
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
