<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Infrastructure\Git;

use Gitonomy\Git\Exception\ProcessException;
use RuntimeException;

/**
 * Exception thrown when a Git command fails.
 */
final class GitException extends RuntimeException
{
    public static function fromProcessException(ProcessException $exception): self
    {
        return new self($exception->getMessage(), $exception->getCode(), $exception);
    }
}
