<?php

namespace Yiisoft\Dev;

use RuntimeException;
use function file_exists;

class Tools
{
    private const USE_HTTP = true;
    private const BASE_DIR = __DIR__ . '/../dev';

    public static function clearLinks(string $package): void
    {
        $targetPath = self::getPackageDir(self::getPackageList()[$package]);
        foreach (self::findDirs("$targetPath/vendor/yiisoft") as $yiisoftPackage) {
            if (is_link($link = "$targetPath/vendor/yiisoft/$yiisoftPackage")) {
                self::unlink($link);
            }
        }
    }

    public static function getPackageDir(string $packageName): string
    {
        return self::BASE_DIR . DIRECTORY_SEPARATOR . $packageName;
    }

    public static function getPackageList(): array
    {
        return (new PackageStorage())->packages;
    }

    /**
     * Finds linkable applications.
     * @param string $dir directory to search in
     * @return array list of applications command can link
     */
    public static function findDirs(string $dir): array
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

    public static function unlink(string $path): bool
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

    public static function installPackage(string $package): void
    {
        $targetPath = self::getPackageDir(self::getPackageList()[$package]);
        Console::stdOut('Installing package  ');
        Console::stdOut($package, 33);

        $repo = (self::USE_HTTP ? 'https://github.com/' : 'git@github.com:') . $package . '.git';

        if (file_exists($targetPath)) {
            Console::stdOutLn(' - already installed', 32);

            return;
        }
        Console::stdOutLn('...');

        passthru('git clone ' . escapeshellarg($repo) . ' ' . escapeshellarg($targetPath));

        Console::stdOutLn('done.', 32);
    }

    public static function relinkVendorDirectories(array $installedPackages): void
    {
        Console::stdErrLn('Re-linking vendor directories...');
        foreach (self::getPackageList() as $package => $dir) {
            Console::stdErrLn($package);
            self::linkPackages($package, $installedPackages);
        }
    }

    public static function linkPackages(string $package, array $installedPackages): void
    {
        $targetPath = self::getPackageDir(self::getPackageList()[$package]);
        foreach ($installedPackages as $installedPackage => $installedPath) {
            if ($package === $installedPackage) {
                continue;
            }
            if (file_exists("$targetPath/vendor/$installedPackage")) {
                // rm dir and replace it with link
                self::removeDirectory("$targetPath/vendor/$installedPackage");
                symlink($installedPath, "$targetPath/vendor/$installedPackage");
            }
        }
    }

    public static function removeDirectory(string $directory): void
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
                    self::removeDirectory($path);
                } else {
                    self::unlink($path);
                }
            }
            closedir($handle);
        }
        if (is_link($directory)) {
            self::unlink($directory);
        } else {
            rmdir($directory);
        }
    }

    public static function getInstalledPackages(): array
    {
        $installedPackages = [];
        foreach (self::getPackageList() as $package => $dir) {
            if (file_exists(self::getPackageDir($dir))) {
                $installedPackages[$package] = self::getPackageDir($dir);
            }
        }
        return $installedPackages;
    }

    public static function composerInstall(string $package): void
    {
        $targetPath = self::getPackageDir(self::getPackageList()[$package]);
        if (!is_file("$targetPath/composer.json")) {
            Console::stdOut('no composer.json in ');
            Console::stdOut($package, 33);
            Console::stdOutLn(', skipping composer install.');

            return;
        }
        Console::stdOut('composer install in ');
        Console::stdOut($package, 33);
        Console::stdOutLn('...');

        $command = 'composer install --prefer-dist --no-progress --working-dir '
            . escapeshellarg($targetPath)
            . (Console::isColor() ? ' --ansi' : ' --no-ansi');
        passthru($command);
        Console::stdOutLn('done.', 32);
    }

    public static function gitCommitPackage(string $package, string $message): void
    {
        $targetPath = self::getPackageDir(self::getPackageList()[$package]);
        Console::stdOutLn($package, 32);
        $command = 'cd ' . escapeshellarg($targetPath)
            . ' && git add . && git commit -m ' . escapeshellarg($message);
        $output = trim(shell_exec($command));

        if (!empty($output)) {
            echo $output . "\n\n";
        }
    }

    public static function gitPushPackage(string $package): void
    {
        $targetPath = self::getPackageDir(self::getPackageList()[$package]);
        Console::stdOutLn($package, 32);
        $command = 'cd ' . escapeshellarg($targetPath) . ' && git push';
        $output = trim(shell_exec($command));

        if (!empty($output)) {
            echo $output . "\n\n";
        }
    }

    public static function gitPackageStatus(string $package): void
    {
        $targetPath = self::getPackageDir(self::getPackageList()[$package]);
        $command = 'cd ' . escapeshellarg($targetPath) . ' && git status -s';
        $output = trim(shell_exec($command));
        Console::stdOutLn($package, empty($output) ? 32 : 33);
        if (!empty($output)) {
            echo $output . "\n\n";
        }
    }

    public static function gitComposerUpdate(string $package): void
    {
        $targetPath = self::getPackageDir(self::getPackageList()[$package]);
        if (!is_file("$targetPath/composer.json")) {
            Console::stdOut('no composer.json in ');
            Console::stdOut($package, 33);
            Console::stdOutLn(', skipping composer update.');

            return;
        }
        Console::stdOut('composer update in ');
        Console::stdOut($package, 33);
        Console::stdOutLn('...');

        $command = 'composer update --prefer-dist --no-progress --working-dir '
            . escapeshellarg($targetPath) . (Console::isColor() ? ' --ansi' : ' --no-ansi');
        passthru($command);
        Console::stdOutLn('done.', 32);
    }

    public static function replicatePackage(string $package, string $sourcePath, array $sourceFiles): void
    {
        $targetPath = self::getPackageDir(self::getPackageList()[$package]);
        Console::stdOut("$package ", 32);

        if (!file_exists($targetPath)) {
            Console::stdOutLn('❌');
            return;
        }

        foreach ($sourceFiles as $file) {
            self::copy(
                $sourcePath . DIRECTORY_SEPARATOR . $file,
                $targetPath . DIRECTORY_SEPARATOR . $file
            );
        }

        Console::stdOutLn('✔');
    }

    public static function copy(string $source, string $target): void
    {
        $destinationDirectory = dirname($target);
        if (!file_exists($destinationDirectory)
            && !mkdir($destinationDirectory, 0777, true) && !is_dir($destinationDirectory)
        ) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $destinationDirectory));
        }
        if (!copy($source, $target)) {
            throw new RuntimeException(sprintf('Copy "%s" to "%s" failed', $source, $target));
        }
    }
}
