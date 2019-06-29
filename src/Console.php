<?php

namespace Yiisoft\Dev;

class Console
{
    public static function stdOutLn(string $text, int $color = null): void
    {
        self::stdOut(self::ansiColor($text, $color) . PHP_EOL);
    }

    public static function stdOut(string $text, int $color = null): void
    {
        fwrite(STDOUT, self::ansiColor($text, $color));
    }

    /**
     * @param string $text the text to print.
     * @param int|null $color color according to https://en.wikipedia.org/wiki/ANSI_escape_code#3/4_bit
     * @return string
     */
    public static function ansiColor(string $text, int $color = null): string
    {
        if ($color !== null && self::isColor()) {
            return "\e[{$color}m$text\e[0m";
        }
        return $text;
    }

    public static function isColor(): bool
    {
        return DIRECTORY_SEPARATOR === '\\'
            ? getenv('ANSICON') !== false || getenv('ConEmuANSI') === 'ON'
            : function_exists('posix_isatty') && @posix_isatty(STDOUT) && @posix_isatty(STDERR);
    }

    public static function help(): void
    {

        self::stdErrLn(<<<YII
  _   _  _  _ 
 | | | |(_)(_)
 | |_| || || |  Development Tool
  \__, ||_||_|
  |___/         for Yii 3.0

YII
        );
        self::stderrln('This tool helps with setting up a development environment for Yii 3 packages.');
        self::stderrln('');
        self::stderrln('Usage: ' . ($_SERVER['argv'][0] ?? 'yii-dev') . ' <command>');
        self::stderrln('');
        self::stderrln('Available Commands:');
        self::stderrln('');

        self::stderr('  install', 33);
        self::stderrln('             Install all packages listed in packages.php or package specified');

        self::stderr('  install', 33);
        self::stderr(' <package>', 34);
        self::stderrln('   Install a single package. <package> refers to the array key in packages.php');

        self::stderr('  update', 33);
        self::stderrln('              Update all packages listed in packages.php or package specified');

        self::stderr('  update', 33);
        self::stderr(' <package>', 34);
        self::stderrln('    Update a single package. <package> refers to the array key in packages.php');

        self::stderr('  status', 33);
        self::stderrln('              Show git status for all packages');

        self::stderr('  replicate', 33);
        self::stderrln('           Copy files specified in replicate.php into each package or package specified');

        self::stderr('  commit', 33);
        self::stderrln('              Add and commit changes into each repository');

        self::stderr('  push', 33);
        self::stderrln('                Push changes into each repository');

        self::stderr('  lint', 33);
        self::stderrln('                Check packages for common mistakes');
        self::stderrln('');
    }

    public static function stdErrLn(string $text, int $color = null): void
    {
        self::stderr(self::ansiColor($text, $color) . PHP_EOL, $color);
    }

    public static function stdErr(string $text, int $color = null): void
    {
        fwrite(STDERR, self::ansiColor($text, $color));
    }
}
