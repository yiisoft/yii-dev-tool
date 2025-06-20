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
use Yiisoft\YiiDevTool\App\Component\GitHubTokenAware;

final class ForksRepositoriesCommand extends Command
{
    use GitHubTokenAware;

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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ownerRepository = $input->getArgument('owner');
        $repositories = $input->getArgument('repositories');

        $targetRepositories = array_unique(explode(',', $repositories));

        $client = new Client();
        $client->authenticate($this->getGitHubToken(), null, AuthMethod::ACCESS_TOKEN);
        $forks = (new Forks($client));

        foreach ($targetRepositories as $repository) {
            try {
                // See https://developer.github.com/v3/repos/forks/
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

    /**
     * Use this method to get a root directory of the tool.
     *
     * Commands and components can be moved as a result of refactoring,
     * so you should not rely on their location in the file system.
     *
     * @return string Path to the root directory of the tool WITH a TRAILING SLASH.
     */
    protected function getAppRootDir(): string
    {
        return rtrim($this
                ->getApplication()
                ->getRootDir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
}
