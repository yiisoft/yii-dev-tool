<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Test\App\Command\Release;

use Yiisoft\YiiDevTool\App\Command\Release\GitHubReleaseInspectorInterface;

final class FakeGitHubReleaseInspector implements GitHubReleaseInspectorInterface
{
    public int $defaultBranchCalls = 0;
    public int $inspectCalls = 0;

    /** @var list<array{name: string, status: string, conclusion: ?string}> */
    public array $checks = [
        ['name' => 'phpunit', 'status' => 'completed', 'conclusion' => 'success'],
        ['name' => 'roave_bc_check', 'status' => 'completed', 'conclusion' => 'skipped'],
    ];

    /** @var array<int, string> */
    public array $issueStates = [12 => 'closed'];

    public function __construct(private string $sha)
    {
    }

    public function getDefaultBranches(array $repositories): array
    {
        $this->defaultBranchCalls++;
        $result = [];
        foreach ($repositories as $key => $_repository) {
            $result[$key] = ['branch' => 'master', 'sha' => $this->sha];
        }

        return $result;
    }

    public function inspect(array $repositories): array
    {
        $this->inspectCalls++;
        $result = [];
        foreach ($repositories as $key => $repository) {
            $issueStates = [];
            foreach ($repository['issues'] as $issue) {
                $issueStates[$issue] = $this->issueStates[$issue] ?? 'closed';
            }
            $result[$key] = [
                'checks' => $this->checks,
                'issueStates' => $issueStates,
            ];
        }

        return $result;
    }
}
