<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Infrastructure\Git;

/**
 * A simple container for tag information returned from a Git repository.
 * Provides methods similar to symplify/git-wrapper's GitTags class.
 */
final class GitTags
{
    /**
     * @param string[] $tags List of all tag names.
     */
    public function __construct(
        private array $tags,
    ) {
    }

    /**
     * Returns all tag names.
     *
     * @return string[]
     */
    public function all(): array
    {
        return $this->tags;
    }
}
