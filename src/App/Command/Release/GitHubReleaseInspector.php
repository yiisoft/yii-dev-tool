<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Release;

use Github\Api\Issue;
use Github\Api\Repo;
use Github\Api\Repository\Checks\CheckRuns;
use Github\AuthMethod;
use Github\Client;

final class GitHubReleaseInspector implements GitHubReleaseInspectorInterface
{
    private Client $client;

    public function __construct(string $token)
    {
        $this->client = new Client();
        $this->client->authenticate($token, null, AuthMethod::ACCESS_TOKEN);
    }

    public function getDefaultBranch(string $vendor, string $repository): array
    {
        $api = new Repo($this->client);
        $repositoryData = $api->show($vendor, $repository);
        $branchName = $repositoryData['default_branch'];
        $branchData = $api->branches($vendor, $repository, $branchName);

        return [
            'branch' => $branchName,
            'sha' => $branchData['commit']['sha'],
        ];
    }

    public function getCheckRuns(string $vendor, string $repository, string $sha): array
    {
        $data = (new CheckRuns($this->client))->allForReference(
            $vendor,
            $repository,
            $sha,
            ['filter' => 'latest', 'per_page' => 100],
        );

        return array_map(
            static fn(array $check): array => [
                'name' => $check['name'],
                'status' => $check['status'],
                'conclusion' => $check['conclusion'],
            ],
            $data['check_runs'],
        );
    }

    public function getIssueState(string $vendor, string $repository, int $issue): string
    {
        return (new Issue($this->client))->show($vendor, $repository, $issue)['state'];
    }
}
