<?php

namespace Yiisoft\YiiDevTool\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\YiiDevTool\Component\Console\PackageCommand;

class UpdateCommand extends PackageCommand
{
    protected function configure()
    {
        $this
            ->setName('update')
            ->setDescription('Update packages');

        $this->addPackageArgument();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var InstallCommand $installCommand */
        $installCommand = $this->getApplication()->find('install')->useUpdateMode();

        /** @noinspection PhpUnhandledExceptionInspection */
        $installCommand->run(new ArrayInput([
            'packages' => $input->getArgument('packages'),
        ]), $output);

        return self::EXIT_SUCCESS;
    }
}
