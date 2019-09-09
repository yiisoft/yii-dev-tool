<?php

namespace yiidev\commands;

use Color;

class PullCommand
{
    private $package;

    // TODO implement setting these
    public $baseDir = __DIR__ . '/../dev';

    public function __construct(string $package = null)
    {
        $this->package = $package;
    }

    public function run(): void
    {
        $packages = require __DIR__ . '/../packages.php';

        if ($this->package === null) {
            // install all packages
            foreach ($packages as $p => $dir) {
                $targetPath = $this->baseDir . DIRECTORY_SEPARATOR . $dir;
                $this->pull($p, $targetPath);
            }
        } elseif (isset($packages[$this->package])) {
            $targetPath = $this->baseDir . DIRECTORY_SEPARATOR . $packages[$this->package];
            $this->pull($this->package, $targetPath);
        } else {
            stderrln("Package '$this->package' not found in packages.php");
            exit(1);
        }
    }

    private function pull(string $package, string $targetPath): void
    {
        stdoutln($package, Color::GREEN);
        $command = 'cd ' . escapeshellarg($targetPath) . ' && git pull';
        $output = trim(shell_exec($command));

        if (!empty($output)) {
            echo $output . "\n\n";
        }
    }
}
