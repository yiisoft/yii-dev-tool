<?php

namespace Yiisoft\YiiDevTool\Component\Console;

use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\YiiDevTool\Component\Package\Package;
use Yiisoft\YiiDevTool\Component\Package\PackageList;

class PackageCommand extends Command
{
    /** @var YiiDevToolStyle */
    private $io;

    /** @var array|null */
    private $targetPackageIds;

    /** @var PackageList */
    private $packageList;

    protected function addPackageArgument(): void
    {
        $this->addArgument(
            'packages',
            InputArgument::OPTIONAL,
            <<<DESCRIPTION
Package names separated by commas. For example: <fg=cyan;options=bold>rbac,di,yii-demo,db-mysql</>
Array keys from <fg=blue;options=bold>package.php</> configuration can be specified.
If packages are not specified, then command will be applied to <fg=yellow>all packages.</>
DESCRIPTION
        );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new YiiDevToolStyle($input, $output);
    }

    protected function getIO(): YiiDevToolStyle
    {
        return $this->io;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = $this->getIO();

        try {
            $this->packageList = new PackageList(__DIR__ . '/../../../packages.php');
        } catch (InvalidArgumentException $e) {
            $io->error([
                'Invalid packages configuration.',
                $e->getMessage(),
                'See file <file>packages.local.php.example</file> for configuration examples.',
            ]);

            exit(1);
        }

        $commaSeparatedPackageIds = $input->getArgument('packages');

        if ($commaSeparatedPackageIds !== null) {
            $this->targetPackageIds = array_unique(explode(',', $commaSeparatedPackageIds));

            foreach ($this->targetPackageIds as $targetPackageId) {
                if (!$this->packageList->hasPackage($targetPackageId)) {
                    $io->error([
                        "Package <package>$targetPackageId</package> not found in <file>packages.php</file>.",
                        'Execution aborted.',
                    ]);

                    exit(1);
                }
            }
        }
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
            $packages[] = $packageList->getPackage($targetId);
        }

        return $packages;
    }

    protected function showPackageErrors(): void
    {
        $io = $this->getIO();
        $packagesWithError = $this->getPackageList()->getPackagesWithError();

        if (count($packagesWithError)) {
            $io->error([
                '=======================================================================',
                'SUMMARY OF ERRORS THAT OCCURED',
                '=======================================================================',
            ]);

            foreach ($packagesWithError as $package) {
                $io->header("Package <package>{$package->getId()}</package> error occurred during <fg=yellow>{$package->getErrorDuring()}</>:");
                $io->writeln($package->getError());
                $io->newLine();
            }
        }
    }
}
