<?php

namespace yiidev\commands;

class CommitCommand
{
    private $package;
    private $message;

    // TODO implement setting these
    public $baseDir = __DIR__ . '/../dev';

    public function __construct(string $message = null, string $package = null)
    {
        if ($message === null) {
            stderrln('Message is required.');
            exit(1);
        }

        $this->package = $package;
        $this->message = $message;
    }

    public function run(): void
    {
        $packages = require __DIR__ . '/../packages.php';

        if ($this->package === null) {
            // install all packages
            foreach ($packages as $p => $dir) {
                $targetPath = $this->baseDir . DIRECTORY_SEPARATOR . $dir;
                $this->commit($p, $targetPath);
            }
        } elseif (isset($packages[$this->package])) {
            $targetPath = $this->baseDir . DIRECTORY_SEPARATOR . $packages[$this->package];
            $this->commit($this->package, $targetPath);
        } else {
            stderrln("Package '$this->package' not found in packages.php");
            exit(1);
        }
    }

    private function commit(string $package, string $targetPath): void
    {
        stdoutln($package, 32);
        $command = 'cd ' . escapeshellarg($targetPath) . ' && git add . && git commit -m ' . escapeshellarg($this->message);
        $output = trim(shell_exec($command));

        if (!empty($output)) {
            echo $output . "\n\n";
        }
    }
}
