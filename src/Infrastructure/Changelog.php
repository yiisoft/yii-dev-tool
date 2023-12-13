<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Infrastructure;

use InvalidArgumentException;

final class Changelog
{
    public const TYPES = [
        'Chg',
        'Bug',
        'New',
        'Enh',
    ];

    public function __construct(private string $path)
    {
    }

    public function resort(): void
    {
        // split the file into relevant parts
        [$start, $rawChangelog, $end] = $this->splitChangelog();

        $changelog = [];
        foreach ($rawChangelog as $i => $line) {
            $line = rtrim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^- (New|Chg|Enh|Bug)( #\d+(, #\d+)*)?: .+/', $line, $m)) {
                $o = ['New' => 'C', 'Chg' => 'D', 'Enh' => 'E', 'Bug' => 'F'];
                $key = $o[$m[1]] . ' ' . (!empty($m[2]) ? $m[2] : 'AAAA') . $i;
            } else {
                $key = 'B' . $i;
            }

            $changelog[$key] = $line;
        }
        ksort($changelog);

        file_put_contents($this->path, implode("\n", array_merge($start, $changelog, $end)));
    }

    public function open(Version $version): void
    {
        $headline = "## $version under development\n";
        $headline .= "\n- no changes in this release.\n";

        $lines = explode("\n", file_get_contents($this->path));
        $hl = [
            array_shift($lines),
            array_shift($lines),
        ];
        array_unshift($lines, $headline);

        file_put_contents($this->path, implode("\n", [...$hl, ...$lines]));
    }

    public function addEntry(string $text): void
    {
        $replaces = $this->replaceInFile(
            '/^(## \d+\.\d+\.\d+ under development\n)\n(?:- no changes in this release\.|- Initial release\.)$/m',
            <<<MARKDOWN
            $1
            - $text
            MARKDOWN,
            $this->path
        );
        if ($replaces === 0) {
            $this->replaceInFile(
                '/^(##\s\d+\.\d+\.\d+\sunder\sdevelopment\n)$/m',
                <<<MARKDOWN
            $1
            - $text
            MARKDOWN,
                $this->path
            );
        }
    }

    public function close(Version $version): void
    {
        $this->replaceInFile(
            '/\d+\.\d+\.\d+ under development/',
            $version . ' ' . date('F d, Y'),
            $this->path
        );
    }

    /**
     * @return string[]
     */
    public function getReleaseNotes(Version $version): array
    {
        [, $changelog] = $this->splitChangelog($version->asString());

        return $changelog;
    }

    /**
     * @return string[]
     */
    public function getReleaseLog(?Version $version = null): array
    {
        return $this->splitChangelog($version?->asString());
    }

    private function replaceInFile(string $pattern, string $replace, string $file): int
    {
        $replaces = null;
        if (!file_exists($file)) {
            throw new InvalidArgumentException(
                sprintf(
                    'File path "%s" is incorrect. The file does not exist.',
                    $file,
                )
            );
        }
        file_put_contents($file, preg_replace($pattern, $replace, file_get_contents($file), count: $replaces));
        return $replaces;
    }

    /**
     * @param string|null $version Version of package or null for "under development".
     */
    private function splitChangelog(?string $version = null): array
    {
        $lines = explode("\n", file_get_contents($this->path));

        // split the file into relevant parts
        $start = [];
        $changelog = [];
        $end = [];

        $state = 'start';
        foreach ($lines as $lineNumber => $line) {
            // starting from the changelogs headline
            if (
                isset($lines[$lineNumber - 2])
                && str_starts_with($lines[$lineNumber - 2], '## ' . ($version === null ? '' : ($version . ' ')))
                && (
                    $version !== null
                    || str_ends_with($lines[$lineNumber - 2], 'under development')
                )
            ) {
                $state = 'changelog';
            }
            if ($state === 'changelog' && isset($lines[$lineNumber + 1]) && str_starts_with(
                $lines[$lineNumber + 1],
                '## '
            )) {
                $state = 'end';
            }
            // add continued lines to the last item to keep them together
            if (!empty(${$state}) && trim($line) !== '' && !str_starts_with($line, '- ')) {
                ${$state}[array_key_last(${$state})] .= "\n" . $line;
            } else {
                ${$state}[] = $line;
            }
        }

        return [$start, $changelog, $end];
    }
}
