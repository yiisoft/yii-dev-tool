<?php

namespace yiidev\components\console;

class Args
{
    /** @var string */
    private $script = null;

    /** @var string */
    private $command = null;

    /** @var string */
    private $package = null;

    /** @var bool */
    private $verbose = false;

    /** @var $bool */
    private $http = false;

    /**
     *
     */
    public function __construct()
    {
        if (!isset($argv)) {
            $argv = $_SERVER['argv'];
            $argc = $_SERVER['argc'];
        }

        $opts    = getopt('hv', ['http','verbose'], $last);
        $package = array_slice($argv, $last);

        $this->script    = $argv[0];
        $this->command   = $package[0] ?? null;
        $this->package   = $package[1] ?? null;


        $this->verbose = array_key_exists('verbose', $opts) || array_key_exists('v', $opts);
        $this->http    = array_key_exists('http', $opts) || array_key_exists('h', $opts);
    }

    /**
     * @return string formatted text
     */
    public function getCommand(): ?string
    {
        return $this->command;
    }

    /**
     * @return bool Setting of the Http flag
     */
    public function getHttp(): bool
    {
        return $this->http;
    }

    /**
     * @return string formatted text
     */
    public function getPackage(): ?string
    {
        return $this->package;
    }

    /**
     * @return string formatted text
     */
    public function getScript(): ?string
    {
        return $this->script;
    }

    /**
     * @return bool Setting of the Verbose flag
     */
    public function getVerbose(): bool
    {
        return $this->verbose;
    }
}
