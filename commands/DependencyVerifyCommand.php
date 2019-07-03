<?php

namespace yiidev\commands;

use Color;

class DependencyVerifyCommand
{
    private const THREADS_MAX = 5;

    public $packagesDir = __DIR__ . '/../dev';
    public $logFile = __DIR__ . '/../runtime/composer-verify.log';
    public $lockFile = __DIR__ . '/../runtime/composer-verify.lock';
    private $errored = [];
    private $isParent = true;

    public function run(array $allowed = [])
    {
        $packages = require __DIR__ . '/../packages.php';

        for ($i = 0; $i < self::THREADS_MAX; $i++) {
            $shouldWork = $this->fork();
            if ($shouldWork) {
                break;
            }
        }

        if ($this->isParent) {
            $start = microtime(true);

            if (file_exists($this->logFile)) {
                unlink($this->logFile);
            }
            if (file_exists($this->lockFile)) {
                unlink($this->lockFile);
            }

            touch($this->logFile);
            touch($this->lockFile);
        }

        foreach ($packages as $package => $directory) {
            if ($shouldWork && ($allowed === [] || in_array($package, $allowed, true))) {
                if ($this->canUse($package)) {
                    $targetPath = $this->packagesDir . DIRECTORY_SEPARATOR . $directory;

                    if (!is_dir($targetPath)) {
                        stderrln("Package $package does not exist", Color::YELLOW);
                    } elseif (file_exists($targetPath . DIRECTORY_SEPARATOR . 'composer.json')) {
                        $this->composerUpdate($package, $targetPath);
                    }
                }
            }
        }

        if ($this->isParent) {
            $this->waitForks();

            $time = round(microtime(true) - $start, 2);
            stdoutln('');
            stdoutln('');
            stdoutln("Finished in $time sec. You can see error details (if any) below and in $this->logFile", Color::GREEN);
            stdoutln('');
            stdoutln(file_get_contents($this->logFile));
        }
    }

    private function composerUpdate(string $package, string $targetPath)
    {
        $path = escapeshellarg($targetPath);
        $command = "composer update --prefer-dist --no-progress --working-dir $path " . (ENABLE_COLOR ? ' --ansi' : ' --no-ansi') . ' 2>&1';
        $output = [];

        exec($command, $output, $result);

        if ($result === 0) {
            stdoutln("✔ $package", Color::GREEN);
        } else {
            $this->errored[] = $package;

            $file = fopen($this->logFile, 'ab');
            flock($file, LOCK_EX);
            fwrite($file, implode(PHP_EOL, $output));
            fwrite($file, PHP_EOL . str_repeat('-', 10) . PHP_EOL);
            flock($file, LOCK_UN);

            stdoutln("❌ $package", Color::RED);
        }
    }

    private function canUse(string $package)
    {
        $result = false;
        $file = fopen($this->lockFile, 'a+b');
        flock($file, LOCK_EX);
        fseek($file, 0);
        $content = @fread($file, filesize($this->lockFile));
        $locked = explode(PHP_EOL, $content);

        if (!in_array($package, $locked)) {
            fwrite($file, PHP_EOL . $package);
            $result = true;
        }

        flock($file, LOCK_UN);
        fclose($file);

        return $result;
    }

    private function fork(): bool
    {
        if (function_exists('pcntl_fork')) {
            $pid = pcntl_fork();

            if ($pid < 0) {
                stdoutln("Cannot fork", Color::RED);
                exit(1);
            }

            if ($pid === 0) {
                $this->isParent = false;

                // We are a fork, so we should make the main work
                return true;
            }

            // We just created a fork, so we shouldn't do anything
            return false;
        }

        // We can't create a fork, so we must do our best ourselves
        return true;
    }

    private function waitForks()
    {
        if (function_exists('pcntl_wait')) {
            do {
                $pid = pcntl_wait($status);
            } while ($pid >= 0);
        }
    }
}
