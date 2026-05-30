<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Test\App\Command\Release;

use PHPUnit\Framework\TestCase;
use Yiisoft\YiiDevTool\App\Command\Release\ReleaseNews;

final class ReleaseNewsTest extends TestCase
{
    public function testGetChangesWithMultilineChangelogItem(): void
    {
        $releaseNews = new ReleaseNews();

        $this->assertSame(
            [
                '- Minor refactor `RequestBodyParser`: use `str_contains()` function instead of `strpos()` and `::class` instead of `get_class()`',
                '- Explicitly mark nullable parameters',
            ],
            $releaseNews->getChanges(
                [
                    "- Enh #47: Minor refactor `RequestBodyParser`: use `str_contains()` function instead of `strpos()`\n   and `::class` instead\n  of `get_class()` (@vjik)",
                    '- Bug #44: Explicitly mark nullable parameters (@vjik)',
                ]
            )
        );
    }
}
