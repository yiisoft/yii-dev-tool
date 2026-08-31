<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Release;

use Github\AuthMethod;
use Github\Client;
use RuntimeException;

use function array_chunk;
use function array_key_exists;
use function implode;
use function sprintf;
use function str_repeat;
use function str_replace;
use function strtolower;

final class GitHubReleaseInspector implements GitHubReleaseInspectorInterface
{
    private const BATCH_SIZE = 20;
    private const DEFAULT_BRANCH_BATCH_SIZE = 200;

    private Client $client;

    public function __construct(string $token)
    {
        $this->client = new Client();
        $this->client->authenticate($token, null, AuthMethod::ACCESS_TOKEN);
    }

    public function getDefaultBranches(array $repositories): array
    {
        $result = [];
        foreach (array_chunk($repositories, self::DEFAULT_BRANCH_BATCH_SIZE, true) as $batch) {
            $fields = [];
            $variables = [];
            $keys = [];
            $index = 0;
            foreach ($batch as $key => $repository) {
                $alias = 'repository' . $index;
                $keys[$alias] = $key;
                $variables["vendor$index"] = $repository['vendor'];
                $variables["name$index"] = $repository['repository'];
                $fields[] = <<<GRAPHQL
                $alias: repository(owner: \$vendor$index, name: \$name$index) {
                  defaultBranchRef { name target { oid } }
                }
                GRAPHQL;
                $index++;
            }

            $definitions = [];
            for ($i = 0; $i < $index; $i++) {
                $definitions[] = "\$vendor$i: String!";
                $definitions[] = "\$name$i: String!";
            }
            $query = sprintf(
                "query (%s) {\n%s\n}",
                implode(', ', $definitions),
                $this->indent(implode("\n", $fields), 2),
            );
            $data = $this->client->graphql()->execute($query, $variables)['data'] ?? [];
            foreach ($keys as $alias => $key) {
                $defaultBranch = $data[$alias]['defaultBranchRef'] ?? null;
                if ($defaultBranch === null) {
                    throw new RuntimeException("GitHub default branch data is unavailable for $key.");
                }
                $result[$key] = [
                    'branch' => $defaultBranch['name'],
                    'sha' => $defaultBranch['target']['oid'],
                ];
            }
        }

        return $result;
    }

    public function inspect(array $repositories): array
    {
        $result = [];
        foreach (array_chunk($repositories, self::BATCH_SIZE, true) as $batch) {
            $result += $this->inspectBatch($batch);
        }

        return $result;
    }

    /**
     * @param array<string, array{
     *     vendor: string,
     *     repository: string,
     *     sha: string,
     *     issues: list<int>
     * }> $repositories
     * @return array<string, array{
     *     checks: list<array{name: string, status: string, conclusion: ?string}>,
     *     issueStates: array<int, string>
     * }>
     */
    private function inspectBatch(array $repositories): array
    {
        $fields = [];
        $variables = [];
        $keys = [];
        $index = 0;

        foreach ($repositories as $key => $repository) {
            $alias = 'repository' . $index;
            $keys[$alias] = $key;
            $variables["vendor$index"] = $repository['vendor'];
            $variables["name$index"] = $repository['repository'];
            $variables["sha$index"] = $repository['sha'];
            $issueFields = [];
            foreach ($repository['issues'] as $issue) {
                $issueFields[] = <<<GRAPHQL
                issue$issue: issueOrPullRequest(number: $issue) {
                  ... on Issue { state }
                  ... on PullRequest { state }
                }
                GRAPHQL;
            }

            $fields[] = <<<GRAPHQL
            $alias: repository(owner: \$vendor$index, name: \$name$index) {
              object(expression: \$sha$index) {
                ... on Commit {
                  statusCheckRollup {
                    contexts(first: 100) {
                      nodes {
                        ... on CheckRun { name status conclusion }
                        ... on StatusContext { context state }
                      }
                    }
                  }
                }
              }
              {$this->indent(implode("\n", $issueFields), 2)}
            }
            GRAPHQL;
            $index++;
        }

        $variableDefinitions = [];
        for ($i = 0; $i < $index; $i++) {
            $variableDefinitions[] = "\$vendor$i: String!";
            $variableDefinitions[] = "\$name$i: String!";
            $variableDefinitions[] = "\$sha$i: String!";
        }
        $query = sprintf(
            "query (%s) {\n%s\n}",
            implode(', ', $variableDefinitions),
            $this->indent(implode("\n", $fields), 2),
        );
        $response = $this->client->graphql()->execute($query, $variables);
        $data = $response['data'] ?? [];
        $result = [];

        foreach ($keys as $alias => $key) {
            if (!array_key_exists($alias, $data) || $data[$alias] === null) {
                throw new RuntimeException("GitHub repository data is unavailable for $key.");
            }

            $repositoryData = $data[$alias];
            $checks = [];
            foreach ($repositoryData['object']['statusCheckRollup']['contexts']['nodes'] ?? [] as $check) {
                if (isset($check['name'])) {
                    $checks[] = [
                        'name' => $check['name'],
                        'status' => strtolower($check['status']),
                        'conclusion' => isset($check['conclusion']) ? strtolower($check['conclusion']) : null,
                    ];
                } else {
                    $state = strtolower($check['state']);
                    $checks[] = [
                        'name' => $check['context'],
                        'status' => $state === 'pending' ? 'pending' : 'completed',
                        'conclusion' => $state === 'success' ? 'success' : ($state === 'pending' ? null : 'failure'),
                    ];
                }
            }

            $issueStates = [];
            foreach ($repositories[$key]['issues'] as $issue) {
                $state = strtolower($repositoryData["issue$issue"]['state'] ?? 'unknown');
                $issueStates[$issue] = $state === 'merged' ? 'closed' : $state;
            }
            $result[$key] = [
                'checks' => $checks,
                'issueStates' => $issueStates,
            ];
        }

        return $result;
    }

    private function indent(string $value, int $spaces): string
    {
        return str_replace("\n", "\n" . str_repeat(' ', $spaces), $value);
    }
}
