<?php

namespace Yiisoft\YiiDevTool\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;
use Yiisoft\YiiDevTool\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\Component\Package\Package;
use Yiisoft\YiiDevTool\Component\Package\ReplicationSource;

class ReplicateCommand extends PackageCommand
{
    /** @var ReplicationSource */
    private $replicationSource;

    protected function configure()
    {
        $this
            ->setName('replicate')
            ->setDescription('Copy files specified in replicate.php into each package');

        $this->addPackageArgument();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $replicationConfig = require __DIR__ . '/../../replicate.php';

        $this->replicationSource = new ReplicationSource(
            $replicationConfig['sourcePackage'],
            $replicationConfig['sourceFiles']
        );

        $this->checkReplicationSource();

        foreach ($this->getTargetPackages() as $package) {
            $this->replicateToPackage($package);
        }

        $this->showPackageErrors();
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
                "  ./yii-dev install $replicationSourcePackageId",
                '',
                'Replication aborted.',
            ]);

            exit(1);
        }
    }

    private function replicateToPackage(Package $package): void
    {
        $replicationSource = $this->replicationSource;
        $io = $this->getIO();
        $header = "Replication to package <package>{$package->getId()}</package>";

        if ($package->getId() === $replicationSource->getPackageId()) {
            if ($this->areTargetPackagesSpecifiedExplicitly()) {
                $io->header($header);
                $io->warning([
                    'Cannot replicate into itself.',
                    "Package <package>{$package->getId()}</package> skipped.",
                ]);
            }

            return;
        }

        if (!$package->doesPackageDirectoryExist()) {
            if ($this->areTargetPackagesSpecifiedExplicitly() || $package->enabled()) {
                $io->header($header);
                $io->error([
                    'An error occurred during replication.',
                    "Package directory <file>{$package->getPath()}</file> does not exist.",
                    'Package replication aborted.',
                ]);

                $package->setError('Package directory does not exist.', "replication");
            }

            return;
        }

        $io->header($header);

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
