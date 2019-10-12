<?php

namespace yiidev\components\helpers;

use RuntimeException;

final class FileHelper
{
    public static function findDirectoriesIn(string $targetDirectoryPath): array
    {
        $list = [];
        $handle = @opendir($targetDirectoryPath);
        if ($handle === false) {
            return [];
        }
        while (($file = readdir($handle)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $targetDirectoryPath . DIRECTORY_SEPARATOR . $file;
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

    public static function removeDirectory(string $targetDirectoryPath): void
    {
        if (!is_dir($targetDirectoryPath)) {
            return;
        }
        if (!is_link($targetDirectoryPath)) {
            if (!($handle = opendir($targetDirectoryPath))) {
                return;
            }
            while (($file = readdir($handle)) !== false) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $path = $targetDirectoryPath . '/' . $file;
                if (is_dir($path)) {
                    static::removeDirectory($path);
                } else {
                    static::unlink($path);
                }
            }
            closedir($handle);
        }
        if (is_link($targetDirectoryPath)) {
            static::unlink($targetDirectoryPath);
        } else {
            rmdir($targetDirectoryPath);
        }
    }

    public static function copy(string $sourcePath, string $targetPath): void
    {
        $destinationDirectory = dirname($targetPath);
        if (!file_exists($destinationDirectory)) {
            if (!mkdir($destinationDirectory, 0777, true) && !is_dir($destinationDirectory)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $destinationDirectory));
            }
        }
        if (!copy($sourcePath, $targetPath)) {
            throw new RuntimeException(sprintf('Copy "%s" to "%s" failed', $sourcePath, $targetPath));
        }
    }
}
