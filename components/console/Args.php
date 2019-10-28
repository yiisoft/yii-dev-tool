<?php

namespace yiidev\components\console;

class Args
{
    /** @var array */
    private $argv = [];

    /** @var string */
    private $script;

    /** @var string|null */
    private $command;

    public function __construct()
    {
        global $argv;

        $this->argv = isset($argv) ? $argv : $_SERVER['argv'];
        $this->script = $this->argv[0] ?? 'yii-dev';
        $this->command = $this->argv[1] ?? null;
    }

    public function getScript(): string
    {
        return $this->script;
    }

    public function getCommand(): ?string
    {
        return $this->command;
    }

    public function getCommandArg(int $position): ?string
    {
        return $this->argv[$position + 1] ?? null;
    }
}
