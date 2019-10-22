<?php

namespace yiidev\commands;

use Throwable;
use yiidev\components\console\Color;
use yiidev\components\console\Printer;
use yiidev\components\helpers\FileHelper;
use yiidev\components\package\PackageCommand;
use yiidev\components\package\Package;
use yiidev\components\package\ReplicationSource;

class ReplicateCommand extends PackageCommand
{
    /** @var ReplicationSource */
    private $replicationSource;

    public function __construct(Printer $printer, string $commaSeparatedPackageIds = null)
    {
        parent::__construct($printer, $commaSeparatedPackageIds);

        $replicationConfig = require __DIR__ . '/../replicate.php';

        $this->replicationSource = new ReplicationSource(
            $replicationConfig['sourcePackage'],
            $replicationConfig['sourceFiles']
        );
    }

    public function run(): void
    {
        $this->checkReplicationSource();

        foreach ($this->getTargetPackages() as $package) {
            $this->replicateToPackage($package);
        }

        $this->showPackageErrors();
    }

    private function checkReplicationSource(): void
    {
        $packageList = $this->getPackageList();
        $replicationSourcePackageId = $this->replicationSource->getPackageId();

        if (!$packageList->hasPackage($replicationSourcePackageId)) {
            $this->getPrinter()
                ->stderr('Package ', Color::LIGHT_RED)
                ->stderr($replicationSourcePackageId, Color::CYAN)
                ->stderrln(' is configured as replication source.', Color::LIGHT_RED)
                ->stderrln('But such a package is not declared in packages configuration.', Color::LIGHT_RED)
                ->stderrln('Replication aborted.', Color::LIGHT_RED);

            exit(1);
        }

        $replicationSourcePackage = $packageList->getPackage($replicationSourcePackageId);
        if (!$replicationSourcePackage->isGitRepositoryCloned()) {
            $this->getPrinter()
                ->stderr('Package ', Color::LIGHT_RED)
                ->stderr($replicationSourcePackageId, Color::CYAN)
                ->stderrln(' is configured as replication source.', Color::LIGHT_RED)
                ->stderrln('But such a package is not installed.', Color::LIGHT_RED)
                ->stderrln('To fix, run the following command:', Color::LIGHT_RED)
                ->stderrln()
                ->stderrln("  ./yii-dev install $replicationSourcePackageId", Color::LIGHT_RED)
                ->stderrln()
                ->stderrln('Replication aborted.', Color::LIGHT_RED);

            exit(1);
        }
    }

    private function printOperationHeader(Package $package): void
    {
        $this->getPrinter()
            ->stdoutln('-----------------------------------------------------------------------')
            ->stdout('Replication to package ')
            ->stdoutln($package->getId(), Color::CYAN)
            ->stdoutln('-----------------------------------------------------------------------')
            ->stdoutln();
    }

    private function replicateToPackage(Package $package): void
    {
        $replicationSource = $this->replicationSource;
        $printer = $this->getPrinter();

        if ($package->getId() === $replicationSource->getPackageId()) {
            if ($this->areTargetPackagesSpecifiedExplicitly()) {
                $this->printOperationHeader($package);

                $printer
                    ->stdoutln('Cannot replicate into itself.', Color::YELLOW)
                    ->stdout('Package ', Color::YELLOW)
                    ->stdout($package->getId(), Color::CYAN)
                    ->stdoutln(' skipped.', Color::YELLOW)
                    ->stdoutln();
            }

            return;
        }

        if (!$package->doesPackageDirectoryExist()) {
            if ($this->areTargetPackagesSpecifiedExplicitly() || $package->enabled()) {
                $this->printOperationHeader($package);

                $package->setError('Package directory does not exist.', "replication");

                $printer
                    ->stderrln('An error occurred during replication.', Color::LIGHT_RED)
                    ->stderr('Package directory ', Color::LIGHT_RED)
                    ->stderr($package->getPath(), Color::LIGHT_BLUE)
                    ->stderrln(' does not exist.', Color::LIGHT_RED)
                    ->stderrln('Package replication aborted.', Color::LIGHT_RED)
                    ->stderrln();
            }

            return;
        }

        $this->printOperationHeader($package);

        $replicationSourcePackage = $this->getPackageList()->getPackage($replicationSource->getPackageId());

        foreach ($replicationSource->getSourceFiles() as $sourceFile) {
            try {
                FileHelper::copy(
                    $replicationSourcePackage->getPath() . DIRECTORY_SEPARATOR . $sourceFile,
                    $package->getPath() . DIRECTORY_SEPARATOR . $sourceFile
                );
            } catch (Throwable $e) {
                $package->setError($e->getMessage(), 'replication');

                $printer
                    ->stderr('An error occurred during replication file ', Color::LIGHT_RED)
                    ->stderrln($sourceFile, Color::LIGHT_BLUE)
                    ->stderrln($e->getMessage(), Color::LIGHT_RED)
                    ->stderrln('Package replication aborted.', Color::LIGHT_RED)
                    ->stderrln();

                return;
            }
        }

        $printer
            ->stdoutln('✔ Done.', Color::GREEN)
            ->stdoutln();
    }
}
