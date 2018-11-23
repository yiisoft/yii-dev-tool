<?php

namespace yiidev\commands;

/**
 *
 *
 * @author Carsten Brandt <mail@cebe.cc>
 */
class InstallCommand
{
    private $package;

    // TODO implement setting these
    public $useHttp = false;
    public $baseDir = __DIR__ . '/../dev';

    public function __construct($package = null)
    {
        $this->package = $package;
    }

    public function run()
    {
        $packages = require __DIR__ . '/../packages.php';

        if ($this->package === null) {
            // install all packages
            foreach($packages as $p => $dir) {
                $targetPath = $this->baseDir . DIRECTORY_SEPARATOR . $dir;
                $this->install($p, $targetPath);
                $this->clearlinks($p, $targetPath);
                $this->composerInstall($p, $targetPath);
            }
        } elseif (isset($packages[$this->package])) {
            $this->install($this->package, $packages[$this->package]);
            $this->clearlinks($this->package, $packages[$this->package]);
            $this->composerInstall($this->package, $packages[$this->package]);
        } else {
            stderrln("Package '$this->package' not found in packages.php");
            exit(1);
        }

        $installedPackages = [];
        foreach($packages as $p => $dir) {
            if (file_exists($this->baseDir . DIRECTORY_SEPARATOR . $dir)) {
                $installedPackages[$p] = $this->baseDir . DIRECTORY_SEPARATOR . $dir;
            }
        }

        stderrln("Re-linking vendor directories...");
        foreach($packages as $p => $dir) {
            stderrln($p);
            $targetPath = $this->baseDir . DIRECTORY_SEPARATOR . $dir;
            $this->linkPackages($p, $targetPath, $installedPackages);
        }
        stdoutln("done.", 32);
    }

    private function install($package, $targetPath)
    {
        stdout("Installing package  ");
        stdout($package, 33);

        $repo = ($this->useHttp ? 'https://github.com/' : 'git@github.com:') . $package . '.git';

        if (file_exists($targetPath)) {
            stdoutln(" - already installed", 32);
            return;
        }
        stdoutln("...");

        passthru('git clone ' . escapeshellarg($repo) . ' ' . escapeshellarg($targetPath));

        stdoutln("done.", 32);
    }

    private function clearLinks($package, $targetPath)
    {
        $yiisoftPackages = $this->findDirs("$targetPath/vendor/yiisoft");
        foreach ($yiisoftPackages as $yp) {
            if (is_link($link = "$targetPath/vendor/yiisoft/$yp")) {
                unlink($link);
            }
        }
    }

    private function composerInstall($package, $targetPath)
    {
        if (!is_file("$targetPath/composer.json")) {
            stdout("no composer.json in ");
            stdout($package, 33);
            stdoutln(", skipping composer install.");
            return;
        }
        stdout("composer install in ");
        stdout($package, 33);
        stdoutln("...");

        $command = 'composer install --prefer-dist --no-progress --working-dir ' . escapeshellarg($targetPath) . (ENABLE_COLOR ? ' --ansi' : ' --no-ansi');
        passthru($command);
        stdoutln("done.", 32);
    }

    private function linkPackages($package, $targetPath, $installedPackages)
    {
        foreach ($installedPackages as $installedPackage => $installedPath) {
            if ($package === $installedPackage) {
                continue;
            }
            if (file_exists("$targetPath/vendor/$installedPackage")) {
                // rm dir and replace it with link
                passthru('rm -rf ' . escapeshellarg("$targetPath/vendor/$installedPackage"));
                symlink($installedPath, "$targetPath/vendor/$installedPackage");
            }
        }
    }

    /**
     * Finds linkable applications.
     *
     * @param string $dir directory to search in
     * @return array list of applications command can link
     */
    protected function findDirs($dir)
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
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $list[] = $file;
            }
        }
        closedir($handle);

        return $list;
    }
}
