<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Component\Console;

use Closure;
use Symfony\Component\Process\Process;

final class ProcessOutput
{
    public static function callback(OutputManager $io): Closure
    {
        return static function (string $type, string $data) use ($io): void {
            $io
                ->important()
                ->write($data);
        };
    }

    public static function run(Process $process, OutputManager $io): int
    {
        return $process->run(self::callback($io));
    }
}
