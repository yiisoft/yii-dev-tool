<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Component\Git;

use ArrayIterator;
use IteratorAggregate;

/**
 * @implements IteratorAggregate<int, string>
 */
final class GitBranches implements IteratorAggregate
{
    public function __construct(private GitWorkingCopy $gitWorkingCopy)
    {
    }

    /**
     * @return string[]
     */
    public function all(): array
    {
        return $this->fetchBranches();
    }

    public function head(): string
    {
        return trim($this->gitWorkingCopy->run('rev-parse', ['--abbrev-ref', 'HEAD']));
    }

    /**
     * @return ArrayIterator<int, string>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->all());
    }

    /**
     * @return string[]
     */
    private function fetchBranches(): array
    {
        $output = trim($this->gitWorkingCopy->branch(['a' => true]));
        if ($output === '') {
            return [];
        }

        return array_map(
            static fn (string $branch): string => ltrim($branch, ' *'),
            preg_split('~\R~', $output) ?: []
        );
    }
}
