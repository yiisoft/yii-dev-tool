<?php

namespace yiidev\components\console;

class Executor
{
    /** @var int */
    private $lastResult;

    /** @var string */
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
        return $this->lastResult;
    }

    public function getLastOutput(): string
    {
        return $this->lastOutput;
    }

    public function hasErrorOccurred(): int
    {
        return $this->lastResult > 0;
    }
}
