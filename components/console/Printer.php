<?php

namespace yiidev\components\console;

class Printer
{
    /** @var bool */
    private $isColorsEnabled;

    public function __construct()
    {
        $this->isColorsEnabled = DIRECTORY_SEPARATOR === '\\' ?
            getenv('ANSICON') !== false || getenv('ConEmuANSI') === 'ON' :
            function_exists('posix_isatty') && @posix_isatty(STDOUT) && @posix_isatty(STDERR);
    }

    public function isColorsEnabled(): bool
    {
        return $this->isColorsEnabled;
    }

    /**
     * @param string $text the text to print.
     * @param int|null $color color according to https://en.wikipedia.org/wiki/ANSI_escape_code#3/4_bit
     * @return string formatted text
     */
    private function colorize(?string $text, int $color = null): ?string
    {
        if ($color !== null && $this->isColorsEnabled) {
            return "\e[{$color}m$text\e[0m";
        }

        return $text;
    }

    public function stdout(?string $text, int $color = null): self
    {
        fwrite(STDOUT, $this->colorize($text, $color));

        return $this;
    }

    public function stdoutln(?string $text = null, int $color = null): self
    {
        $this->stdout($this->colorize($text, $color) . PHP_EOL);

        return $this;
    }

    public function stderr(?string $text, int $color = null): self
    {
        fwrite(STDERR, $this->colorize($text, $color));

        return $this;
    }

    public function stderrln(?string $text = null, int $color = null): self
    {
        $this->stderr($this->colorize($text, $color) . PHP_EOL, $color);

        return $this;
    }
}
