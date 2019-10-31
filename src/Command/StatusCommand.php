<?php

namespace Yiisoft\YiiDevTool\Command;

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
            ->setName('status')
            ->setDescription('Show git status of packages');

        $this->addPackageArgument();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        foreach ($this->getTargetPackages() as $package) {
            $this->showGitStatus($package);
        }
    }

    private function showGitStatus(Package $package): void
    {
        $io = $this->getIO();
        $header = "Git status of package <package>{$package->getId()}</package>";

        if (!$package->isGitRepositoryCloned()) {
            if ($this->areTargetPackagesSpecifiedExplicitly()) {
                $io->header($header);
                $io->warning([
                    'The package repository is not cloned.',
                    'Git status check skipped.',
                ]);
            }

            return;
        }

        $io->header($header);

        $process = new Process(['git', 'status', '-s'], $package->getPath());
        $process->run();
        $output = $process->getOutput();

        if (empty($output)) {
            $io->success('✔ nothing to commit, working tree clean');
        } else {
            $io->writeln($output);
        }
    }
}
