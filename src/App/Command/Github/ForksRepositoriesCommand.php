<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Github;

use Github\Api\Repository\Forks;
use Github\AuthMethod;
use Github\Client;
use Github\Exception\RuntimeException as GithubRuntimeException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\YiiDevTool\App\Component\Console\OutputManager;
use Yiisoft\YiiDevTool\App\Component\Console\YiiDevToolStyle;
use Yiisoft\YiiDevTool\App\YiiDevToolApplication;

/** @method YiiDevToolApplication getApplication() */
final class ForksRepositoriesCommand extends Command
{
    private ?OutputManager $io = null;

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new OutputManager(new YiiDevToolStyle($input, $output));
    }

    protected function getIO(): OutputManager
    {
        if ($this->io === null) {
            throw new RuntimeException('IO is not initialized.');
        }

        return $this->io;
    }

    protected function configure()
    {
        $this
            ->setAliases(['forks'])
            ->setName('github/forks')
            ->setDescription('Creating forks for repositories')
            ->addArgument('owner', InputArgument::REQUIRED, 'Repositories owner')
            ->addArgument('repositories', InputArgument::REQUIRED, 'upstream repositories');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ownerRepository = $input->getArgument('owner');
        $repositories = $input->getArgument('repositories');

        $targetRepositories = array_unique(explode(',', $repositories));

        $client = new Client();
        $client->authenticate($this->getToken(), null, AuthMethod::ACCESS_TOKEN);
        $forks = (new Forks($client));

        foreach ($targetRepositories as $repository) {
            try {
                // See http://developer.github.com/v3/repos/forks/
                $forks->create($ownerRepository, $repository);
                $output->write("<success>Successfully forked repository: $repository</success>" . PHP_EOL);
            } catch (GithubRuntimeException $e) {
                $output->write(
                    [
                        "<error>Error when forking a repository $repository: {$e->getMessage()}</error>",
                        '<error>Check if the nickname of the owner and the name of the repository are correct</error>' . PHP_EOL,
                    ],
                    true
                );
            }
        }
        return Command::SUCCESS;
    }

    private function getToken(): string
    {
        return $this
            ->getApplication()
            ->getConfig()
            ->getApiToken();
    }
}
