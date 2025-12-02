<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Infrastructure\Git;

/**
 * A simple container for branch information returned from a Git repository.
 * Provides methods similar to symplify/git-wrapper's GitBranches class.
 */
final class GitBranches
{
    /**
     * @param string[] $branches List of all branch names.
     * @param string $head Current branch name (HEAD).
     */
    public function __construct(
        private array $branches,
        private string $head,
    ) {
    }

    /**
     * Returns all branch names.
     *
     * @return string[]
     */
    public function all(): array
    {
        return $this->branches;
    }

    /**
     * Returns the current HEAD branch name.
     */
    public function head(): string
    {
        return $this->head;
    }
}
