<?php

namespace Yiisoft\YiiDevTool\Command\Replicate;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;
use Yiisoft\YiiDevTool\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\Component\Package\Package;
use Yiisoft\YiiDevTool\Component\Package\ReplicationSource;

class ReplicateFilesCommand extends PackageCommand
{
    private ReplicationSource $replicationSource;

    protected function configure()
    {
        $this
            ->setName('replicate/files')
            ->setDescription('Copy files specified in <fg=blue;options=bold>config/replicate/files.php</> into each package');

        $this->addPackageArgument();
    }

    protected function beforeProcessingPackages(InputInterface $input): void
    {
        $replicationConfig = require __DIR__ . '/../../../config/replicate/files.php';

        $this->replicationSource = new ReplicationSource(
            $replicationConfig['sourcePackage'],
            $replicationConfig['sourceFiles']
        );

        $this->checkReplicationSource();
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<success>✔ Done</success>';
    }

    private function checkReplicationSource(): void
    {
        $io = $this->getIO();
        $packageList = $this->getPackageList();
        $replicationSourcePackageId = $this->replicationSource->getPackageId();

        if (!$packageList->hasPackage($replicationSourcePackageId)) {
            $io->error([
                "Package <package>$replicationSourcePackageId</package> is configured as replication source.",
                'But such a package is not declared in packages configuration.',
                'Replication aborted.',
            ]);

            exit(1);
        }

        $replicationSourcePackage = $packageList->getPackage($replicationSourcePackageId);
        if (!$replicationSourcePackage->isGitRepositoryCloned()) {
            $io->error([
                "Package <package>$replicationSourcePackageId</package> is configured as replication source.",
                'But such a package is not installed.',
                'To fix, run the following command:',
                '',
                "  <cmd>./yii-dev install $replicationSourcePackageId</cmd>",
                '',
                'Replication aborted.',
            ]);

            exit(1);
        }
    }

    protected function processPackage(Package $package): void
    {
        $replicationSource = $this->replicationSource;
        $io = $this->getIO();
        $io->preparePackageHeader($package, "Replication to package {package}");

        if ($package->getId() === $replicationSource->getPackageId()) {
            if ($this->areTargetPackagesSpecifiedExplicitly()) {
                $io->warning([
                    'Cannot replicate into itself.',
                    "Package <package>{$package->getId()}</package> skipped.",
                ]);
            }

            return;
        }

        $replicationSourcePackage = $this->getPackageList()->getPackage($replicationSource->getPackageId());

        $fs = new Filesystem();
        foreach ($replicationSource->getSourceFiles() as $sourceFile) {
            try {
                $fs->copy(
                    $replicationSourcePackage->getPath() . DIRECTORY_SEPARATOR . $sourceFile,
                    $package->getPath() . DIRECTORY_SEPARATOR . $sourceFile
                );
            } catch (Throwable $e) {
                $io->error([
                    "An error occurred during replication file <file>{$sourceFile}</file>",
                    $e->getMessage(),
                    'Package replication aborted.',
                ]);

                $package->setError($e->getMessage(), 'replication');

                return;
            }
        }

        $io->done();
    }
}
