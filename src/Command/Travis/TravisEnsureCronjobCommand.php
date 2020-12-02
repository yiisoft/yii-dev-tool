<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Command\Travis;

use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\Infrastructure\Travis\API\TravisClient;

class TravisEnsureCronjobCommand extends PackageCommand
{
    private bool $useOrg;
    private TravisClient $travisClient;

    protected function configure()
    {
        $this
            ->setName('travis/ensure-cronjob')
            ->setDescription('Ensures that a daily cronjob is set for <fg=yellow;options=bold>master</> branch of package')
            ->addOption(
                'org',
                null,
                InputOption::VALUE_NONE,
                'Use <fg=yellow;options=bold>travis-ci.org</> instead of <fg=yellow;options=bold>travis-ci.com</>'
            );

        $this->addPackageArgument();
    }

    protected function beforeProcessingPackages(InputInterface $input): void
    {
        $io = $this->getIO();

        $apiConfigPath = $this->getAppRootDir() . 'config/travis/api.local.php';
        if (!file_exists($apiConfigPath)) {
            $io->error([
                "Configuration <file>config/travis/api.local.php</file> not found.",
                "You can create it by example <file>config/travis/api.local.php.example</file>",
            ]);

            exit(1);
        }

        /** @noinspection PhpIncludeInspection */
        $apiConfig = require $apiConfigPath;

        $this->useOrg = $input->getOption('org');
        $tokenKeyInConfig = $this->useOrg ? 'org-token' : 'com-token';

        if (empty($apiConfig[$tokenKeyInConfig])) {
            $io->error([
                "API access token must be specified in configuration <file>config/travis/api.local.php</file>",
                "See <file>config/travis/api.local.php.example</file> for example.",
            ]);

            exit(1);
        }

        $this->travisClient = new TravisClient($apiConfig[$tokenKeyInConfig], $this->useOrg);
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<success>✔ Done</success>';
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, "Adjusting Travis cronjob for package {package}");

        $travisClient = $this->travisClient;
        $travisDomain = $this->useOrg ? 'travis-ci.org' : 'travis-ci.com';
        $packageSlug = urlencode("yiisoft/{$package->getId()}");

        try {
            $io->info("Retrieving existing cronjobs using <em>$travisDomain</em>...");
            $cronsData = $travisClient->get("/repo/{$packageSlug}/crons");

            if (count($cronsData['crons']) === 0) {
                $io->info("Cronjobs not found.");
                $this->createCronjobForMasterBranch($travisClient, $packageSlug);

                return;
            }

            $io->info("Cronjobs already exist.");
            $io->info("Checking existing cronjobs...");

            $masterCronjob = null;
            foreach ($cronsData['crons'] as $cronjob) {
                if ($cronjob['branch']['name'] === 'master') {
                    $masterCronjob = $cronjob;
                    break;
                }
            }

            if ($masterCronjob === null) {
                $io->info("Cronjob for <em>master</em> branch not found.");
                $this->createCronjobForMasterBranch($travisClient, $packageSlug);

                return;
            }

            $io->info("Cronjob for <em>master</em> branch found.");
            $io->info("Checking the cronjob...");

            if ($masterCronjob['interval'] === 'daily') {
                $io->info("The cronjob has correct daily interval.");
                if ($masterCronjob['dont_run_if_recent_build_exists'] === false) {
                    $io->info("The cronjob run regardless of the latest builds.");
                    $io->success("The cronjob settings are correct!");

                    return;
                } else {
                    $io->info("The cronjob does not run if recent build exists.");
                }
            } else {
                $io->info("The cronjob has incorrect interval: <em>{$masterCronjob['interval']}</em>.");
            }

            $io->info("Some cronjob settings are incorrect.");
            $io->info("Deleting the cronjob...");
            $travisClient->delete("/cron/{$masterCronjob['id']}");
            $io->info("The cronjob has been deleted.");

            $this->createCronjobForMasterBranch($travisClient, $packageSlug);
        } catch (RuntimeException $e) {
            $io->error([
                "An error occurred during adjusting cronjob:",
                $e->getMessage(),
                "The package skipped.",
            ]);
        }
    }

    private function createCronjobForMasterBranch(TravisClient $travisClient, string $packageSlug): void
    {
        $this->getIO()->info("Creating a new daily cronjob for <em>master</em> branch...");
        $travisClient->post("/repo/{$packageSlug}/branch/master/cron", [
            'cron.interval' => 'daily',
            'cron.dont_run_if_recent_build_exists' => false,
        ]);
        $this->getIO()->done();
    }
}
