<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Test\App\Command\Release;

use Yiisoft\YiiDevTool\App\Command\Release\GitHubReleaseInspectorInterface;

final class FakeGitHubReleaseInspector implements GitHubReleaseInspectorInterface
{
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

    public function getDefaultBranch(string $vendor, string $repository): array
    {
        return ['branch' => 'master', 'sha' => $this->sha];
    }

    public function getCheckRuns(string $vendor, string $repository, string $sha): array
    {
        return $this->checks;
    }

    public function getIssueState(string $vendor, string $repository, int $issue): string
    {
        return $this->issueStates[$issue] ?? 'closed';
    }
}
