<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;

final class UpdateCommand extends PackageCommand
{
    protected function configure()
    {
        $this
            ->setName('update')
            ->setDescription('Update packages')
            ->addOption(
                'no-plugins',
                null,
                InputOption::VALUE_NONE,
                'Use <fg=green>--no-plugins</> during <fg=green;options=bold>composer update</>'
            );


        $this->addPackageArgument();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var InstallCommand $installCommand */
        $installCommand = $this->getApplication()->find('install')->useUpdateMode();

        /** @noinspection PhpUnhandledExceptionInspection */
        $installCommand->run(new ArrayInput([
            'packages' => $input->getArgument('packages'),
            '--no-plugins' => $input->getOption('no-plugins'),
        ]), $output);

        return self::EXIT_SUCCESS;
    }
}
