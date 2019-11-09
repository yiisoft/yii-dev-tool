<?php

namespace Yiisoft\YiiDevTool\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Yiisoft\YiiDevTool\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\Component\Package\Package;

class StatusCommand extends PackageCommand
{
    /** @var bool */
    private $changesFound = false;

    protected function configure()
    {
        $this
            ->setName('status')
            ->setDescription('Show git status of packages')
            ->addOption('--verbose', '-v', InputOption::VALUE_NONE, 'Increase the verbosity of messages');

        $this->addPackageArgument();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        foreach ($this->getTargetPackages() as $package) {
            $this->showGitStatus($package);
        }

        $io = $this->getIO();
        if (!$io->isVerbose() && !$this->changesFound) {
            $io->success('✔ nothing to commit, working trees clean');
        }
    }

    private function showGitStatus(Package $package): void
    {
        $io = $this->getIO();

        $header = ($io->isVerbose() ? 'Git status of package ' : '') . "<package>{$package->getId()}</package>";

        $process = new Process(['git', 'status', '-s'], $package->getPath());
        $process->run();
        $output = $process->getOutput();

        if (empty($output)) {
            if ($io->isVerbose()) {
                $io->header($header);
                $io->success('✔ nothing to commit, working tree clean');
            }
        } else {
            $this->changesFound = true;
            $io->header($header);
            $io->writeln($output);
        }
    }
}
