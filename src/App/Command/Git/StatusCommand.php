<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Git;

use Symfony\Component\Process\Process;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;

final class StatusCommand extends PackageCommand
{
    protected static $defaultName = 'git/status';
    protected static $defaultDescription = 'Show git status of packages';

    protected function configure(): void
    {
        $this->setAliases(['status', 's']);

        parent::configure();
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<success>✔ nothing to commit, working trees clean</success>';
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, 'Git status of {package}');

        $branchName = $package->getGitWorkingCopy()->getBranches()->head();

        $process = new Process(['git', 'status', '-s'], $package->getPath());
        $process->run();
        $output = $process->getOutput();

        if (empty($output)) {
            $io->important()->success("[$branchName] ✔ nothing to commit, working tree clean");
            $io->done();
        } else {
            $io->important()->info("[$branchName]" . PHP_EOL . $output);
        }
    }
}
