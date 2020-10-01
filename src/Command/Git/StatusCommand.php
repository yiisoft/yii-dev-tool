<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Command\Git;

use Symfony\Component\Process\Process;
use Yiisoft\YiiDevTool\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\Component\Package\Package;

class StatusCommand extends PackageCommand
{
    protected function configure()
    {
        $this
            ->setName('git/status')
            ->setDescription('Show git status of packages');

        $this->addPackageArgument();
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<success>✔ nothing to commit, working trees clean</success>';
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, 'Git status of {package}');

        $process = new Process(['git', 'status', '-s'], $package->getPath());
        $process->run();
        $output = $process->getOutput();

        if (empty($output)) {
            $io->success('✔ nothing to commit, working tree clean');
        } else {
            $io->important()->info($output);
        }
    }
}
