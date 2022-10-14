<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Infrastructure;

use Closure;
use InvalidArgumentException;
use Yiisoft\Arrays\ArrayHelper;

use function is_array;

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
        [$start, $changelog, $end] = $this->splitChangelog();

        // cleanup whitespace
        foreach ($changelog as $i => $line) {
            $changelog[$i] = rtrim($line);
        }
        $changelog = array_filter($changelog);

        $i = 0;
        $this->multisort($changelog, function ($line) use (&$i) {
            if (preg_match('/^- (New|Chg|Enh|Bug)( #\d+(, #\d+)*)?: .+/', $line, $m)) {
                $o = ['New' => 'C', 'Chg' => 'D', 'Enh' => 'E', 'Bug' => 'F'];
                return $o[$m[1]] . ' ' . (!empty($m[2]) ? $m[2] : 'AAAA' . $i++);
            }

            return 'B' . $i++;
        }, SORT_ASC, SORT_NATURAL);

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

        file_put_contents($this->path, implode("\n", array_merge($hl, $lines)));
    }

    public function addEntry(string $text): void
    {
        $this->replaceInFile(
            '/^(##\s\d+\.\d+\.\d+\sunder\sdevelopment\n)$/m',
            <<<MARKDOWN
            $1
            - $text
            MARKDOWN,
            $this->path
        );
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

    private function replaceInFile($pattern, $replace, $file): void
    {
        file_put_contents($file, preg_replace($pattern, $replace, file_get_contents($file)));
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
            if ($state === 'changelog' && isset($lines[$lineNumber + 1]) && str_starts_with($lines[$lineNumber + 1], '## ')) {
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

    /**
     * Sorts an array of objects or arrays (with the same structure) by one or several keys.
     *
     * @param array $array the array to be sorted. The array will be modified after calling this method.
     * @param array|Closure|string $key the key(s) to be sorted by. This refers to a key name of the sub-array
     * elements, a property name of the objects, or an anonymous function returning the values for comparison
     * purpose. The anonymous function signature should be: `function($item)`.
     * To sort by multiple keys, provide an array of keys here.
     * @param array|int $direction the sorting direction. It can be either `SORT_ASC` or `SORT_DESC`.
     * When sorting by multiple keys with different sorting directions, use an array of sorting directions.
     * @param array|int $sortFlag the PHP sort flag. Valid values include
     * `SORT_REGULAR`, `SORT_NUMERIC`, `SORT_STRING`, `SORT_LOCALE_STRING`, `SORT_NATURAL` and `SORT_FLAG_CASE`.
     * Please refer to [PHP manual](https://secure.php.net/manual/en/function.sort.php)
     * for more details. When sorting by multiple keys with different sort flags, use an array of sort flags.
     *
     * @throws InvalidArgumentException if the $direction or $sortFlag parameters do not have
     * correct number of elements as that of $key.
     */
    private function multisort(&$array, array|Closure|string $key, array|int $direction = SORT_ASC, array|int $sortFlag = SORT_REGULAR): void
    {
        $keys = is_array($key) ? $key : [$key];
        if (empty($keys) || empty($array)) {
            return;
        }
        $n = count($keys);
        if (is_scalar($direction)) {
            $direction = array_fill(0, $n, $direction);
        } elseif (count($direction) !== $n) {
            throw new InvalidArgumentException('The length of $direction parameter must be the same as that of $keys.');
        }
        if (is_scalar($sortFlag)) {
            $sortFlag = array_fill(0, $n, $sortFlag);
        } elseif (count($sortFlag) !== $n) {
            throw new InvalidArgumentException('The length of $sortFlag parameter must be the same as that of $keys.');
        }
        $args = [];
        foreach ($keys as $i => $k) {
            $flag = $sortFlag[$i];
            $args[] = ArrayHelper::getColumn($array, $k);
            $args[] = $direction[$i];
            $args[] = $flag;
        }

        // This fix is used for cases when main sorting specified by columns has equal values
        // Without it it will lead to Fatal Error: Nesting level too deep - recursive dependency?
        $args[] = range(1, count($array));
        $args[] = SORT_ASC;
        $args[] = SORT_NUMERIC;

        $args[] = &$array;
        array_multisort(...$args);
    }
}
