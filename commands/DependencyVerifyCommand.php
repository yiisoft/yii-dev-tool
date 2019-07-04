<?php

namespace yiidev\commands;

use Color;

class DependencyVerifyCommand
{
    private const THREADS_MAX = 5;

    public $packagesDir = __DIR__ . '/../dev';
    public $logFile = __DIR__ . '/../runtime/composer-verify.log';
    public $lockFile = __DIR__ . '/../runtime/composer-verify.lock';
    public $forkLockFile = __DIR__ . '/../runtime/verify-fork.lock';

    public function run(array $allowed = [])
    {
        touch($this->forkLockFile);
        $packages = require __DIR__ . '/../packages.php';

        for ($i = 0; $i < self::THREADS_MAX; $i++) {
            $pid = $this->fork();

            $shouldWork = $pid === -1 || $pid === 0;
            if ($shouldWork) {
                break;
            }
        }

        if ($pid > 0) {
            $this->printForkOk(true);
        } elseif ($pid === -1) {
            $this->printForkOk(false);
        } else {
            // allow parent to clean and create all files
            while (file_exists($this->forkLockFile)) {
                usleep(100);
            }
        }

        if ($pid > 0) {
            $start = microtime(true);
            usleep(100);

            if (file_exists($this->logFile)) {
                unlink($this->logFile);
            }
            touch($this->logFile);

            if (file_exists($this->lockFile)) {
                unlink($this->lockFile);
            }
            touch($this->lockFile);

            unlink($this->forkLockFile);
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

        if ($pid > 0) {
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
            $output = array_merge(["Updating $package:"], $output);

            $file = fopen($this->logFile, 'ab');
            flock($file, LOCK_EX);
            fwrite($file, implode(PHP_EOL, $output));
            fwrite($file, PHP_EOL . str_repeat('-', 40) . PHP_EOL);
            flock($file, LOCK_UN);

            stdoutln("❌ $package", Color::RED);
        }
    }

    private function canUse(string $package)
    {
        $result = false;

        $file = fopen($this->lockFile, 'a+b');
        do {
            $lock = flock($file, LOCK_EX);
        } while(!$lock);

        fseek($file, 0);
        $content = @fread($file, filesize($this->lockFile));
        $locked = explode(PHP_EOL, $content);

        if (!in_array($package, $locked)) {
            fwrite($file, PHP_EOL . $package);
            fflush($file);
            $result = true;
        }

        flock($file, LOCK_UN);
        fclose($file);

        return $result;
    }

    /**
     * @return int Process id if fork is created, 0 if this process is a fork or -1 if we can't use pcntl_fork()
     */
    private function fork(): int
    {
        if (function_exists('pcntl_fork')) {
            $pid = pcntl_fork();

            if ($pid < 0) {
                stderrln("Cannot fork", Color::RED);
                exit(1);
            }

            return $pid;
        }

        return -1;
    }

    private function waitForks()
    {
        if (function_exists('pcntl_wait')) {
            do {
                $pid = pcntl_wait($status);
            } while ($pid >= 0);
        }
    }

    private function printForkOk(bool $ok): void
    {
        if ($ok === true) {
            stdoutln('Forking is ok, running in parallel');
        } else {
            stdoutln('Forking failed, continuing in a single thread');
        }
    }
}
