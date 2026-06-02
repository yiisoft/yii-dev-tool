<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Test\App\Component\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;
use Yiisoft\YiiDevTool\App\Component\Console\OutputManager;
use Yiisoft\YiiDevTool\App\Component\Console\ProcessOutput;
use Yiisoft\YiiDevTool\App\Component\Console\YiiDevToolStyle;

final class ProcessOutputTest extends TestCase
{
    public function testRunStreamsOutputBeforeProcessFinishes(): void
    {
        $sentinel = tempnam(sys_get_temp_dir(), 'yii-dev-tool-process-output-');
        self::assertIsString($sentinel);

        if (file_exists($sentinel)) {
            unlink($sentinel);
        }

        $output = new class ($sentinel) extends BufferedOutput {
            public bool $receivedOutputBeforeProcessFinished = false;

            public function __construct(private string $sentinel)
            {
                parent::__construct();
            }

            protected function doWrite(string $message, bool $newline): void
            {
                if (str_contains($message, 'started') && !file_exists($this->sentinel)) {
                    $this->receivedOutputBeforeProcessFinished = true;
                }

                parent::doWrite($message, $newline);
            }
        };

        $process = new Process([
            PHP_BINARY,
            '-r',
            'echo "started\n"; flush(); usleep(200000); touch($argv[1]); echo "finished\n";',
            $sentinel,
        ]);

        ProcessOutput::run(
            $process,
            new OutputManager(new YiiDevToolStyle(new ArrayInput([]), $output))
        );

        try {
            $this->assertTrue($process->isSuccessful());
            $this->assertTrue($output->receivedOutputBeforeProcessFinished);
            $this->assertStringContainsString('started', $output->fetch());
        } finally {
            if (file_exists($sentinel)) {
                unlink($sentinel);
            }
        }
    }
}
