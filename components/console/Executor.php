<?php

namespace yiidev\components\console;

use RuntimeException;

class Executor
{
    /** @var int|null */
    private $lastResult;

    /** @var string|null */
    private $lastOutput;

    public function execute(string $escapedCommand): self
    {
        $result = 0;
        $output = [];

        exec($escapedCommand . ' 2>&1', $output, $result);

        $this->lastOutput = count($output) ? implode(PHP_EOL, $output) : '';
        $this->lastResult = $result;

        return $this;
    }

    public function getLastResult(): int
    {
        if ($this->lastResult === null) {
            throw new RuntimeException('Method execute() must be called first.');
        }

        return $this->lastResult;
    }

    public function getLastOutput(): string
    {
        if ($this->lastOutput === null) {
            throw new RuntimeException('Method execute() must be called first.');
        }

        return $this->lastOutput;
    }

    public function hasErrorOccurred(): bool
    {
        return $this->getLastResult() > 0;
    }
}
