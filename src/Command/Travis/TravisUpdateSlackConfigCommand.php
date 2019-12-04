<?php

namespace Yiisoft\YiiDevTool\Command\Travis;

use Symfony\Component\Console\Input\InputInterface;
use Yiisoft\YiiDevTool\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\Component\Package\Package;
use Yiisoft\YiiDevTool\Component\Travis\TravisConfig;
use Yiisoft\YiiDevTool\Component\Travis\TravisEncryptor;
use Yiisoft\YiiDevTool\Component\Travis\TravisKeyPuller;

class TravisUpdateSlackConfigCommand extends PackageCommand
{
    /** @var string */
    private $slackToken;

    protected function configure()
    {
        $this
            ->setName('travis/update-slack-config')
            ->setDescription('Generate Slack notification settings and update <fg=blue;options=bold>.travis.yml</> file of each package');

        $this->addPackageArgument();
    }

    protected function beforeProcessingPackages(InputInterface $input): void
    {
        $io = $this->getIO();

        $slackConfigPath = __DIR__ . '/../../../config/travis/slack.local.php';
        if (!file_exists($slackConfigPath)) {
            $io->error([
                "Configuration <file>config/travis/slack.local.php</file> not found.",
                "You can create it by example <file>config/travis/slack.local.php.example</file>",
            ]);

            exit(1);
        }

        /** @noinspection PhpIncludeInspection */
        $slackConfig = require $slackConfigPath;

        if (empty($slackConfig['token'])) {
            $io->error([
                "In configuration <file>config/travis/slack.local.php</file>, Slack token must be specified.",
                "See <file>config/travis/slack.local.php.example</file> for example.",
            ]);

            exit(1);
        }

        $this->slackToken = (string) $slackConfig['token'];
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<success>✔ Done</success>';
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, "Updating Travis config in package {package}");

        $travisYMLPath = "{$package->getPath()}/.travis.yml";
        if (!file_exists($travisYMLPath)) {
            $io->warning([
                "No <file>.travis.yml</file> in the package.",
                "Updating Travis config skipped.",
            ]);

            return;
        }

        $io->info("Pulling repository public key from Travis...");
        $travisKeyPuller = new TravisKeyPuller("yiisoft/{$package->getId()}");
        $repositoryPublicKey = $travisKeyPuller->pullPublicKey();

        $io->info("Encrypting Slack tokens with Travis encrypting algorithm...");
        $encryptor = new TravisEncryptor();
        $encryptor->setPublicKey($repositoryPublicKey);
        $encryptedTokenForSuccessfulBuilds = $encryptor->encrypt("yii:{$this->slackToken}#build-successes");
        $encryptedTokenForFailedBuilds = $encryptor->encrypt("yii:{$this->slackToken}#build-failures");

        $io->info("Updating <file>.travis.yml</file> config...");

        $travisConfig = new TravisConfig($travisYMLPath);
        $travisConfig->updateSlackNotificationsConfig([
            [
                'rooms' => [['secure' => $encryptedTokenForSuccessfulBuilds]],
                'on_success' => 'always',
                'on_failure' => 'never',
                'on_pull_requests' => false,
            ],
            [
                'rooms' => [['secure' => $encryptedTokenForFailedBuilds]],
                'on_success' => 'never',
                'on_failure' => 'always',
                'on_pull_requests' => false,
            ],
        ]);

        $io->done();
    }
}
