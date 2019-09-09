<?php

namespace yiidev\commands;

use Color;

class ReplicateCommand
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
        $replicate = require __DIR__ . '/../replicate.php';

        $sourcePackage = $replicate['sourcePackage'];
        $sourcePath = $this->baseDir . DIRECTORY_SEPARATOR . $packages[$sourcePackage];
        $sourceFiles = $replicate['sourceFiles'];

        if ($this->package === null) {
            // install all packages
            foreach ($packages as $p => $dir) {
                if ($p === $sourcePackage) {
                    // skip source package
                    continue;
                }

                $targetPath = $this->baseDir . DIRECTORY_SEPARATOR . $dir;
                $this->replicate($p, $targetPath, $sourcePath, $sourceFiles);
            }
        } elseif (isset($packages[$this->package])) {
            if ($this->package === $sourcePackage) {
                stderrln('Cannot replicate into itself.');
                exit(1);
            }

            $targetPath = $this->baseDir . DIRECTORY_SEPARATOR . $packages[$this->package];
            $this->replicate($this->package, $targetPath, $sourcePath, $sourceFiles);
        } else {
            stderrln("Package '$this->package' not found in packages.php");
            exit(1);
        }
    }

    private function replicate(string $package, string $targetPath, string $sourcePath, array $sourceFiles): void
    {
        stdout("$package ", Color::GREEN);

        if (!\file_exists($targetPath)) {
            echo stdoutln('❌');
            return;
        }

        foreach ($sourceFiles as $file) {
            $this->copy($sourcePath . DIRECTORY_SEPARATOR . $file, $targetPath . DIRECTORY_SEPARATOR . $file);
        }

        stdoutln('✔');
    }

    private function copy(string $source, string $target): void
    {
        $destinationDirectory = dirname($target);
        if (!file_exists($destinationDirectory)) {
            if (!mkdir($destinationDirectory, 0777, true) && !is_dir($destinationDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $destinationDirectory));
            }
        }
        if (!copy($source, $target)) {
            throw new \RuntimeException(sprintf('Copy "%s" to "%s" failed', $source, $target));
        }
    }
}
