<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App;

use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\ListCommand as ListCommandsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Yiisoft\YiiDevTool\App\Command\Composer\ComposerFixDependenciesCommand;
use Yiisoft\YiiDevTool\App\Command\Composer\UpdateCommand as ComposerUpdateCommand;
use Yiisoft\YiiDevTool\App\Command\ExecCommand;
use Yiisoft\YiiDevTool\App\Command\Git\CheckoutCommand;
use Yiisoft\YiiDevTool\App\Command\Git\CommitCommand;
use Yiisoft\YiiDevTool\App\Command\Git\PullCommand;
use Yiisoft\YiiDevTool\App\Command\Git\PushCommand;
use Yiisoft\YiiDevTool\App\Command\Git\RequestPullCommand;
use Yiisoft\YiiDevTool\App\Command\Git\StatusCommand;
use Yiisoft\YiiDevTool\App\Command\Github\ProtectBranchCommand;
use Yiisoft\YiiDevTool\App\Command\Github\SettingsCommand;
use Yiisoft\YiiDevTool\App\Command\InstallCommand;
use Yiisoft\YiiDevTool\App\Command\LintCommand;
use Yiisoft\YiiDevTool\App\Command\ListCommand;
use Yiisoft\YiiDevTool\App\Command\Release\MakeCommand;
use Yiisoft\YiiDevTool\App\Command\Release\WhatCommand;
use Yiisoft\YiiDevTool\App\Command\Replicate\ReplicateComposerConfigCommand;
use Yiisoft\YiiDevTool\App\Command\Replicate\ReplicateCopyFileCommand;
use Yiisoft\YiiDevTool\App\Command\Replicate\ReplicateFilesCommand;
use Yiisoft\YiiDevTool\App\Command\Stats\ContributorsCommand;
use Yiisoft\YiiDevTool\App\Command\TestCommand;
use Yiisoft\YiiDevTool\App\Command\UpdateCommand;

class YiiDevToolApplication extends Application
{
    private ?string $rootDir = null;

    private string $header = <<<HEADER
    <fg=cyan;options=bold> _   _ </><fg=red;options=bold> _ </><fg=green;options=bold> _ </>
    <fg=cyan;options=bold>| | | |</><fg=red;options=bold>(_)</><fg=green;options=bold>(_)</>  <fg=yellow;options=bold>Development Tool</>
    <fg=cyan;options=bold>| |_| |</><fg=red;options=bold>| |</><fg=green;options=bold>| |</>
    <fg=cyan;options=bold> \__, |</><fg=red;options=bold>|_|</><fg=green;options=bold>|_|</>  <fg=yellow;options=bold>for Yii 3.0</>
    <fg=cyan;options=bold> |___/ </>

    This tool helps with setting up a development environment for Yii 3 packages.
    HEADER;

    public function __construct()
    {
        parent::__construct($this->header);
        $this->setDefaultCommand('list-commands');
    }

    public function setRootDir(string $path): self
    {
        $this->rootDir = $path;

        return $this;
    }

    public function getRootDir(): string
    {
        if ($this->rootDir === null) {
            throw new RuntimeException('The root directory is not configured.');
        }

        return $this->rootDir;
    }

    protected function getDefaultCommands()
    {
        return [
            (new HelpCommand())->setHidden(true),
            (new ListCommandsCommand())->setName('list-commands')->setHidden(true),
            new CheckoutCommand(),
            new CommitCommand(),
            new TestCommand(),
            new RequestPullCommand(),
            new ExecCommand(),
            new ComposerFixDependenciesCommand(),
            new ComposerUpdateCommand(),
            new ListCommand(),
            new InstallCommand(),
            new LintCommand(),
            new PullCommand(),
            new PushCommand(),
            new ReplicateFilesCommand(),
            new ReplicateComposerConfigCommand(),
            new ReplicateCopyFileCommand(),
            new StatusCommand(),
            new UpdateCommand(),
            new WhatCommand(),
            new MakeCommand(),
            new SettingsCommand(),
            new ProtectBranchCommand(),
            new ContributorsCommand(),
        ];
    }

    protected function getDefaultInputDefinition(): InputDefinition
    {
        return new InputDefinition([
            new InputArgument('command', InputArgument::REQUIRED, 'The command to execute'),
            new InputOption('--help', '-h', InputOption::VALUE_NONE, 'Display this help message'),
            new InputOption('--verbose', '-v', InputOption::VALUE_NONE, 'Increase the verbosity of messages'),
        ]);
    }
}
