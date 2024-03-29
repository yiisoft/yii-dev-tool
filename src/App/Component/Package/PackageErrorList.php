<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Component\Package;

use Countable;
use Iterator;

class PackageErrorList implements Iterator, Countable
{
    private array $list = [];

    public function set(Package $package, string $message, string $during): void
    {
        $this->list[$package->getId()] = new PackageError($package, $message, $during);
    }

    public function has(Package $package): bool
    {
        return isset($this->list[$package->getId()]);
    }

    public function count(): int
    {
        return count($this->list);
    }

    public function rewind(): void
    {
        reset($this->list);
    }

    #[\ReturnTypeWillChange]
    public function current()
    {
        return current($this->list);
    }

    public function key(): ?string
    {
        return key($this->list);
    }

    #[\ReturnTypeWillChange]
    public function next()
    {
        return next($this->list);
    }

    public function valid(): bool
    {
        return key($this->list) !== null;
    }
}
