<?php

namespace yiidev\components\package;

use InvalidArgumentException;
use yiidev\components\console\Color;
use yiidev\components\console\Executor;
use yiidev\components\console\Printer;

class PackageCommand
{
    /** @var Printer */
    private $printer;

    /** @var Executor|null */
    private $executor;

    /** @var array|null */
    private $targetPackageIds;

    /** @var PackageList */
    private $packageList;

    public function __construct(Printer $printer, string $commaSeparatedPackageIds = null)
    {
        $this->printer = $printer;

        if ($commaSeparatedPackageIds !== null) {
            $this->targetPackageIds = explode(',', $commaSeparatedPackageIds);
        }

        try {
            $this->packageList = new PackageList(__DIR__ . '/../../packages.php');
        } catch (InvalidArgumentException $e) {
            $printer
                ->stderrln('Invalid packages configuration.', Color::LIGHT_RED)
                ->stderrln($e->getMessage(), Color::LIGHT_RED)
                ->stderr('See file ', Color::LIGHT_RED)
                ->stderr('packages.local.php.example', Color::CYAN)
                ->stderrln(' for configuration examples.', Color::LIGHT_RED);

            exit(1);
        }
    }

    protected function getPrinter(): Printer
    {
        return $this->printer;
    }

    protected function getExecutor(): Executor
    {
        if ($this->executor === null) {
            $this->executor = new Executor();
        }

        return $this->executor;
    }

    protected function getPackageList(): PackageList
    {
        return $this->packageList;
    }

    protected function areTargetPackagesSpecifiedExplicitly(): bool
    {
        return $this->targetPackageIds !== null;
    }

    /**
     * @return Package[]
     */
    protected function getTargetPackages(): array
    {
        $targetIds = $this->targetPackageIds;
        $packageList = $this->packageList;

        if ($targetIds === null) {
            return $packageList->getAllPackages();
        }

        $packages = [];
        foreach ($targetIds as $targetId) {
            if (!$packageList->hasPackage($targetId)) {
                $this->printer
                    ->stderr('Package ', Color::LIGHT_RED)
                    ->stderr($targetId, Color::CYAN)
                    ->stderrln(' not found in packages.php', Color::LIGHT_RED)
                    ->stderrln('Execution aborted.', Color::LIGHT_RED);

                exit(1);
            }

            $packages[] = $packageList->getPackage($targetId);
        }

        return $packages;
    }

    protected function showPackageErrors(): void
    {
        $printer = $this->getPrinter();
        $packagesWithError = $this->getPackageList()->getPackagesWithError();

        if (count($packagesWithError)) {
            $printer
                ->stdoutln('=======================================================================', Color::LIGHT_RED)
                ->stdoutln('Summary of errors that occurred', Color::LIGHT_RED)
                ->stdoutln('=======================================================================', Color::LIGHT_RED)
                ->stdoutln();

            foreach ($packagesWithError as $package) {
                $printer
                    ->stdoutln('-----------------------------------------------------------------------')
                    ->stdout('Package ')
                    ->stdout($package->getId(), Color::CYAN)
                    ->stdout(' error occurred during ')
                    ->stdout($package->getErrorDuring(), Color::YELLOW)
                    ->stdoutln(':')
                    ->stdoutln('-----------------------------------------------------------------------')
                    ->stdoutln()
                    ->stdoutln($package->getError())
                    ->stdoutln();
            }
        }
    }
}
