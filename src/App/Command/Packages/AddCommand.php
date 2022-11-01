<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Packages;

use Github\Api\User;
use Github\AuthMethod;
use Github\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\VarDumper\VarDumper;
use Yiisoft\YiiDevTool\App\Component\Console\YiiDevToolStyle;
use Yiisoft\YiiDevTool\App\YiiDevToolApplication;

/** @method YiiDevToolApplication getApplication() */
final class AddCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('packages/add')
            ->setAliases(['add'])
            ->setDescription('Add packages')
            ->addArgument(
                'packages',
                InputArgument::OPTIONAL,
                <<<DESCRIPTION
                Package names separated by commas. For example: <fg=cyan;options=bold>rbac,di,demo,db-mysql</>
                DESCRIPTION
            )
            ->addOption('owner', null, InputOption::VALUE_REQUIRED, 'Packages Owner')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Add all packages')
            ->addOption(
                'perPage',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of requested repositories. Default 30 (мax: 100) '
            )
            ->addOption('page', null, InputOption::VALUE_REQUIRED, 'Page number of the requested repositories list');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new YiiDevToolStyle($input, $output);

        if (!file_exists($this->getApplication()->getConfigFile())) {
            $io->error('The config file does not exist. Initialize the dev tool.');
            exit(1);
        }
        $configs = require $this->getApplication()->getConfigFile();
        $packages = $configs['packages'];

        $allPackages = $input->getOption('all');
        $addPackages = [];
        if ($allPackages) {
            $owner = $input->getOption('owner');
            $perPage = (int)$input->getOption('perPage');
            $page = (int)$input->getOption('page');

            $repositories = $this
                ->getRepositories(
                    $owner ?? $this->getOwner(),
                    $page ?? 1,
                    $perPage ?? 30
                );
            if (empty($repositories)) {
                $io->error('Repository list empty.');

                return Command::FAILURE;
            }

            foreach ($repositories as $repository) {
                if (isset($repository['archived']) && !$repository['archived']) {
                    $addPackages[] = $repository['name'];
                }
            }
        } else {
            $commaSeparatedPackageIds = $input->getArgument('packages');
            if ($commaSeparatedPackageIds === null) {
                $io->error('Please, specify packages separated by commas or use flag "--all".');
                return Command::FAILURE;
            }
            $addPackages = array_unique(explode(',', $commaSeparatedPackageIds));
        }

        foreach ($addPackages as $package) {
            $packages[$package] = true;
        }
        ksort($packages);

        $configs['packages'] = $packages;
        $exportArray = VarDumper::create($configs)->export();
        file_put_contents($this->getApplication()->getConfigFile(), "<?php\n\nreturn $exportArray;\n");

        $io->success('Packages added: ' . implode(', ', $addPackages));
        return Command::SUCCESS;
    }

    private function getRepositories(string $owner, int $page = 1, int $perPage = 30): array
    {
        $client = new Client();
        $client->authenticate($this->getToken(), null, AuthMethod::ACCESS_TOKEN);
        $user = new class ($client) extends User {
            public function userRepositories(
                string $username,
                string $type = 'owner',
                string $sort = 'full_name',
                string $direction = 'asc',
                int $perPage = 30,
                int $page = 1
            ) {
                return $this->get('/users/' . rawurlencode($username) . '/repos', [
                    'type' => $type,
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => $perPage,
                    'page' => $page,
                ]);
            }
        };

        return $user->userRepositories(
            $owner,
            perPage: $perPage,
            page: $page
        );
    }

    private function getToken(): string
    {
        return $this
            ->getApplication()
            ->getConfig()
            ->getApiToken();
    }

    private function getOwner(): string
    {
        return $this
            ->getApplication()
            ->getConfig()
            ->getOwner();
    }
}
