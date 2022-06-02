<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Replicate;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\App\Component\Package\ReplicationSet;

final class ReplicateFilesCommand extends PackageCommand
{
    private array $sets = [];
    private ?array $replicationConfig = null;

    protected function configure()
    {
        $this
            ->setName('replicate/files')
            ->addOption('sets', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Sets to replicate')
            ->setDescription('Copy files specified in <fg=blue;options=bold>config/replicate/files.php</> into each package');

        parent::configure();
    }

    private function getReplicationSet(string $name): ?ReplicationSet
    {
        if ($this->replicationConfig === null) {
            /** @noinspection PhpIncludeInspection */
            $this->replicationConfig = require $this->getAppRootDir() . 'config/replicate/files.php';
        }

        if (!array_key_exists($name, $this->replicationConfig)) {
            return null;
        }

        $setConfig = $this->replicationConfig[$name];

        return new ReplicationSet(
            $setConfig['source'],
            $setConfig['files'],
            $setConfig['packages']['include'],
            $setConfig['packages']['exclude'],
        );
    }

    protected function beforeProcessingPackages(InputInterface $input): void
    {
        $this->sets = $input->getOption('sets');
        foreach ($this->sets as $set) {
            $this->checkReplicationSet($set);
        }
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<success>✔ Done</success>';
    }

    private function checkReplicationSet(string $set): void
    {
        $replicationSet = $this->getReplicationSet($set);

        $io = $this->getIO();

        if ($replicationSet === null) {
            $io->error("There is no \"$set\" replication set.");

            exit(1);
        }

        $packageList = $this->getPackageList();
        $replicationSourcePackageId = $replicationSet->getSourcePackage();

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
                "  <cmd>{$this->getExampleCommandPrefix()}yii-dev install $replicationSourcePackageId</cmd>",
                '',
                'Replication aborted.',
            ]);

            exit(1);
        }
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, 'Replication to package {package}');

        foreach ($this->sets as $set) {
            $replicationSet = $this->getReplicationSet($set);

            if ($package->getId() === $replicationSet->getSourcePackage()) {
                if ($this->areTargetPackagesSpecifiedExplicitly()) {
                    $io->warning([
                        'Cannot replicate into itself.',
                        "Package <package>{$package->getId()}</package> skipped.",
                    ]);
                }

                return;
            }

            if (!$replicationSet->appliesToPackage($package->getId())) {
                $io->info("Skipping package <package>{$package->getId()}</package>.");
                continue;
            }

            $sourcePackage = $this
                ->getPackageList()
                ->getPackage($replicationSet->getSourcePackage());

            $fs = new Filesystem();
            foreach ($replicationSet->getFiles() as $sourceFile) {
                try {
                    $sourceFilePath = $sourcePackage->getPath() . DIRECTORY_SEPARATOR . $sourceFile;
                    $targetFilePath = $package->getPath() . DIRECTORY_SEPARATOR . $sourceFile;

                    $io->info("Copying $sourceFilePath to $targetFilePath.");
                    $fs->copy(
                        $sourceFilePath,
                        $targetFilePath,
                        true
                    );
                } catch (Throwable $e) {
                    $io->error([
                        "An error occurred during replicating file <file>{$sourceFile}</file>",
                        $e->getMessage(),
                        'Package replication aborted.',
                    ]);

                    $this->registerPackageError($package, $e->getMessage(), 'replication');

                    return;
                }
            }
        }

        $io->done();
    }
}
