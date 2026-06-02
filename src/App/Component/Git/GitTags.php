<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Component\Git;

use ArrayIterator;
use IteratorAggregate;

/**
 * @implements IteratorAggregate<int, string>
 */
final class GitTags implements IteratorAggregate
{
    public function __construct(private GitWorkingCopy $gitWorkingCopy)
    {
    }

    /**
     * @return string[]
     */
    public function all(): array
    {
        $output = trim($this->gitWorkingCopy->tag(['l' => true]));
        if ($output === '') {
            return [''];
        }

        return array_map(
            static fn (string $tag): string => ltrim($tag, ' *'),
            preg_split('~\R~', $output) ?: []
        );
    }

    /**
     * @return ArrayIterator<int, string>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->all());
    }
}
