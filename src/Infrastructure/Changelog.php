<?php
declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Infrastructure;

use Yiisoft\Arrays\ArrayHelper;

final class Changelog
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function resort(Version $version): void
    {
        // split the file into relevant parts
        [$start, $changelog, $end] = $this->splitChangelog($version);

        // cleanup whitespace
        foreach ($changelog as $i => $line) {
            $changelog[$i] = rtrim($line);
        }
        $changelog = array_filter($changelog);

        $i = 0;
        $this->multisort($changelog, function ($line) use (&$i) {
            if (preg_match('/^- (Chg|Enh|Bug|New)( #\d+(, #\d+)*)?: .+/', $line, $m)) {
                $o = ['Bug' => 'C', 'Enh' => 'D', 'Chg' => 'E', 'New' => 'F'];
                return $o[$m[1]] . ' ' . (!empty($m[2]) ? $m[2] : 'AAAA' . $i++);
            }

            return 'B' . $i++;
        }, SORT_ASC, SORT_NATURAL);

        // re-add leading and trailing lines
        array_unshift($changelog, '');
        $changelog[] = '';
        $changelog[] = '';

        file_put_contents($this->path, implode("\n", array_merge($start, $changelog, $end)));
    }

    public function open(Version $version): void
    {
        $headline = "\n## $version under development\n";
        $headline .= "\n- no changes in this release.\n";

        $lines = explode("\n", file_get_contents($this->path));
        $hl = [
            array_shift($lines),
            array_shift($lines),
        ];
        array_unshift($lines, $headline);

        file_put_contents($this->path, implode("\n", array_merge($hl, $lines)));
    }

    public function close(Version $version): void
    {
        $this->replaceInFile(
            '/\d+\.\d+\.\d+ under development/',
            $version . ' ' . date('F d, Y'),
            $this->path
        );
    }

    private function replaceInFile($pattern, $replace, $file): void
    {
        file_put_contents($file, preg_replace($pattern, $replace, file_get_contents($file)));
    }

    private function splitChangelog(Version $version): array
    {
        $lines = explode("\n", file_get_contents($this->path));

        // split the file into relevant parts
        $start = [];
        $changelog = [];
        $end = [];

        $state = 'start';
        foreach ($lines as $lineNumber => $line) {
            // starting from the changelogs headline
            if (isset($lines[$lineNumber - 2]) && strpos($lines[$lineNumber - 2], '## ') === 0 && strpos($lines[$lineNumber - 2], $version->asString()) !== false) {
                $state = 'changelog';
            }
            if ($state === 'changelog' && isset($lines[$lineNumber + 1]) && strncmp($lines[$lineNumber + 1], '## ', 3) === 0) {
                $state = 'end';
            }
            // add continued lines to the last item to keep them together
            if (!empty(${$state}) && trim($line) !== '' && strncmp($line, '- ', 2) !== 0) {
                end(${$state});
                ${$state}[key(${$state})] .= "\n" . $line;
            } else {
                ${$state}[] = $line;
            }
        }

        return [$start, $changelog, $end];
    }

    /**
     * Sorts an array of objects or arrays (with the same structure) by one or several keys.
     * @param array $array the array to be sorted. The array will be modified after calling this method.
     * @param string|\Closure|array $key the key(s) to be sorted by. This refers to a key name of the sub-array
     * elements, a property name of the objects, or an anonymous function returning the values for comparison
     * purpose. The anonymous function signature should be: `function($item)`.
     * To sort by multiple keys, provide an array of keys here.
     * @param int|array $direction the sorting direction. It can be either `SORT_ASC` or `SORT_DESC`.
     * When sorting by multiple keys with different sorting directions, use an array of sorting directions.
     * @param int|array $sortFlag the PHP sort flag. Valid values include
     * `SORT_REGULAR`, `SORT_NUMERIC`, `SORT_STRING`, `SORT_LOCALE_STRING`, `SORT_NATURAL` and `SORT_FLAG_CASE`.
     * Please refer to [PHP manual](https://secure.php.net/manual/en/function.sort.php)
     * for more details. When sorting by multiple keys with different sort flags, use an array of sort flags.
     * @throws \InvalidArgumentException if the $direction or $sortFlag parameters do not have
     * correct number of elements as that of $key.
     */
    private function multisort(&$array, $key, $direction = SORT_ASC, $sortFlag = SORT_REGULAR)
    {
        $keys = is_array($key) ? $key : [$key];
        if (empty($keys) || empty($array)) {
            return;
        }
        $n = count($keys);
        if (is_scalar($direction)) {
            $direction = array_fill(0, $n, $direction);
        } elseif (count($direction) !== $n) {
            throw new \InvalidArgumentException('The length of $direction parameter must be the same as that of $keys.');
        }
        if (is_scalar($sortFlag)) {
            $sortFlag = array_fill(0, $n, $sortFlag);
        } elseif (count($sortFlag) !== $n) {
            throw new \InvalidArgumentException('The length of $sortFlag parameter must be the same as that of $keys.');
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
