<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Component;

use Symfony\Component\Console\Command\Command;
use Github\Client;
use Github\AuthMethod;

trait GitHubTokenAware
{
    private ?string $gitHubToken = null;

    public function getGitHubToken(): string
    {
        if ($this->gitHubToken !== null) {
            return $this->gitHubToken;
        }

        $io = $this->getIO();

        $tokenFile = $this->getAppRootDir() . 'config/github.token';
        if (!file_exists($tokenFile)) {
            $io->error([
                "There's no $tokenFile.",
                '<href=https://github.com/settings/tokens>Please create a GitHub token</> and put it there.',
            ]);
            exit(Command::FAILURE);
        }

        $token = trim(file_get_contents($tokenFile));
        if (empty($token)) {
            $io->error([
                "$tokenFile exists but is empty.",
                '<href=https://github.com/settings/tokens>Please create a GitHub token</> and put it there.',
            ]);
            exit(Command::FAILURE);
        }

        // Test the token by making an authenticated request
        $client = new Client();
        $client->authenticate($token, null, AuthMethod::ACCESS_TOKEN);

        try {
            $client->currentUser()->show();
        } catch (\Exception $e) {
            $io->error([
                "Failed to authenticate with GitHub using the provided token from $tokenFile.",
                '<href=https://github.com/settings/tokens>Please make sure the token is valid and has the required permissions</>.',
                'Error: ' . $e->getMessage(),
            ]);
            exit(Command::FAILURE);
        }

        $this->gitHubToken = $token;
        return $token;
    }
}
