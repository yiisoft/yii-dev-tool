<?php

namespace yiidev\commands;

class PushCommand
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
                $this->push($p, $targetPath);
            }
        } elseif (isset($packages[$this->package])) {
            $targetPath = $this->baseDir . DIRECTORY_SEPARATOR . $packages[$this->package];
            $this->push($this->package, $targetPath);
        } else {
            stderrln("Package '$this->package' not found in packages.php");
            exit(1);
        }
    }

    private function push(string $package, string $targetPath): void
    {
        stdoutln($package, 32);
        $command = 'cd ' . escapeshellarg($targetPath) . ' && git push';
        $output = trim(shell_exec($command));

        if (!empty($output)) {
            echo $output . "\n\n";
        }
    }
}
