<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Release;

interface GitHubReleaseInspectorInterface
{
    /**
     * @param array<string, array{vendor: string, repository: string}> $repositories
     * @return array<string, array{branch: string, sha: string}>
     */
    public function getDefaultBranches(array $repositories): array;

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
    public function inspect(array $repositories): array;
}
