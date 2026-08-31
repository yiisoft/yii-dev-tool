<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Release;

interface GitHubReleaseInspectorInterface
{
    /** @return array{branch: string, sha: string} */
    public function getDefaultBranch(string $vendor, string $repository): array;

    /** @return list<array{name: string, status: string, conclusion: ?string}> */
    public function getCheckRuns(string $vendor, string $repository, string $sha): array;

    public function getIssueState(string $vendor, string $repository, int $issue): string;
}
