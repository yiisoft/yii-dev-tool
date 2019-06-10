<?php

namespace yiidev\commands;

class StatusCommand
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
                $this->printStatus($p, $targetPath);
            }
        } elseif (isset($packages[$this->package])) {
            $targetPath = $this->baseDir . DIRECTORY_SEPARATOR . $packages[$this->package];
            $this->printStatus($this->package, $targetPath);
        } else {
            stderrln("Package '$this->package' not found in packages.php");
            exit(1);
        }
    }

    private function printStatus(string $package, string $targetPath): void
    {
        $output = trim(shell_exec('cd ' . escapeshellarg($targetPath) . ' && git status -s'));
        stdoutln("\n$package", empty($output) ? 32 : 33);
        if (!empty($output)) {
            echo $output . "\n";
        }
    }
}
