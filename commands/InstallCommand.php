<?php

namespace yiidev\commands;

/**
 * @author Carsten Brandt <mail@cebe.cc>
 */
class InstallCommand
{
    private $package;

    // TODO implement setting these
    public $useHttp = false;
    public $baseDir = __DIR__.'/../dev';

    public function __construct(string $package = null)
    {
        $this->package = $package;
    }

    public function run(): void
    {
        $packages = require __DIR__.'/../packages.php';

        if ($this->package === null) {
            // install all packages
            foreach ($packages as $p => $dir) {
                $targetPath = $this->baseDir.DIRECTORY_SEPARATOR.$dir;
                $this->install($p, $targetPath);
                $this->clearlinks($targetPath);
                $this->composerInstall($p, $targetPath);
            }
        } elseif (isset($packages[$this->package])) {
            $targetPath = $this->baseDir.DIRECTORY_SEPARATOR.$packages[$this->package];
            $this->install($this->package, $targetPath);
            $this->clearlinks($targetPath);
            $this->composerInstall($this->package, $targetPath);
        } else {
            stderrln("Package '$this->package' not found in packages.php");
            exit(1);
        }

        $installedPackages = [];
        foreach ($packages as $p => $dir) {
            if (file_exists($this->baseDir.DIRECTORY_SEPARATOR.$dir)) {
                $installedPackages[$p] = $this->baseDir.DIRECTORY_SEPARATOR.$dir;
            }
        }

        stderrln('Re-linking vendor directories...');
        foreach ($packages as $p => $dir) {
            stderrln($p);
            $targetPath = $this->baseDir.DIRECTORY_SEPARATOR.$dir;
            $this->linkPackages($p, $targetPath, $installedPackages);
        }
        stdoutln('done.', 32);
    }

    private function install(string $package, string $targetPath): void
    {
        stdout('Installing package  ');
        stdout($package, 33);

        $repo = ($this->useHttp ? 'https://github.com/' : 'git@github.com:').$package.'.git';

        if (file_exists($targetPath)) {
            stdoutln(' - already installed', 32);

            return;
        }
        stdoutln('...');

        passthru('git clone '.escapeshellarg($repo).' '.escapeshellarg($targetPath));

        stdoutln('done.', 32);
    }

    private function clearLinks(string $targetPath): void
    {
        foreach ($this->findDirs("$targetPath/vendor/yiisoft") as $yiisoftPackage) {
            if (is_link($link = "$targetPath/vendor/yiisoft/$yiisoftPackage")) {
                unlink($link);
            }
        }
    }

    private function composerInstall(string $package, string $targetPath): void
    {
        if (!is_file("$targetPath/composer.json")) {
            stdout('no composer.json in ');
            stdout($package, 33);
            stdoutln(', skipping composer install.');

            return;
        }
        stdout('composer install in ');
        stdout($package, 33);
        stdoutln('...');

        $command = 'composer install --prefer-dist --no-progress --working-dir '.escapeshellarg($targetPath).(ENABLE_COLOR ? ' --ansi' : ' --no-ansi');
        passthru($command);
        stdoutln('done.', 32);
    }

    private function linkPackages(string $package, string $targetPath, array $installedPackages): void
    {
        foreach ($installedPackages as $installedPackage => $installedPath) {
            if ($package === $installedPackage) {
                continue;
            }
            if (file_exists("$targetPath/vendor/$installedPackage")) {
                // rm dir and replace it with link
                $this->removeDirectory("$targetPath/vendor/$installedPackage");
                symlink($installedPath, "$targetPath/vendor/$installedPackage");
            }
        }
    }

    /**
     * Finds linkable applications.
     *
     * @param string $dir directory to search in
     *
     * @return array list of applications command can link
     */
    protected function findDirs(string $dir): array
    {
        $list = [];
        $handle = @opendir($dir);
        if ($handle === false) {
            return [];
        }
        while (($file = readdir($handle)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$file;
            if (is_dir($path)) {
                $list[] = $file;
            }
        }
        closedir($handle);

        return $list;
    }

    private function removeDirectory(string $directory)
    {
        if (!is_dir($directory)) {
            return;
        }
        if (!is_link($directory)) {
            if (!($handle = opendir($directory))) {
                return;
            }
            while (($file = readdir($handle)) !== false) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $path = $directory . '/' . $file;
                if (is_dir($path)) {
                    $this->removeDirectory($path);
                } else {
                    $this->unlink($path);
                }
            }
            closedir($handle);
        }
        if (is_link($directory)) {
            $this->unlink($directory);
        } else {
            rmdir($directory);
        }

    }

    private function unlink(string $path): bool
    {
        $isWindows = DIRECTORY_SEPARATOR === '\\';

        if (!$isWindows) {
            return unlink($path);
        }

        if (is_link($path) && is_dir($path)) {
            return rmdir($path);
        }

        return unlink($path);
    }
}
