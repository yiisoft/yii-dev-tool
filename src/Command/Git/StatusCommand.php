<?php

namespace Yiisoft\YiiDevTool\Command\Git;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        foreach ($this->getTargetPackages() as $package) {
            $this->showGitStatus($package);
        }

        $io = $this->getIO();
        $io->clearPreparedPackageHeader();

        if ($io->nothingHasBeenOutput()) {
            $io->important()->success('✔ nothing to commit, working trees clean');
        }
    }

    private function showGitStatus(Package $package): void
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
