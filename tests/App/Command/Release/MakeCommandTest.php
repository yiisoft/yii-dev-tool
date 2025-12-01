<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Test\App\Command\Release;

use PHPUnit\Framework\TestCase;

/**
 * Tests for the release summary generation in MakeCommand.
 */
final class MakeCommandTest extends TestCase
{
    /**
     * Pattern used to strip author information from changelog notes for news text.
     * This matches the pattern: `- Type #issue: Description (@author1, @author2, ...)`
     */
    private const AUTHOR_STRIP_PATTERN = '~^-.*?:\s+(.*)\s+\(.*\)$~';

    public function stripAuthorFromNoteProvider(): array
    {
        return [
            'single author' => [
                '- New #47: Add `filter` option for `FileHelper::clearDirectory()` (@dood-)',
                'Add `filter` option for `FileHelper::clearDirectory()`',
            ],
            'multiple authors' => [
                '- New yiisoft/yii-dev-tool#47: Add `filter` option for `FileHelper::clearDirectory()` (@dood-, @vjik)',
                'Add `filter` option for `FileHelper::clearDirectory()`',
            ],
            'three authors' => [
                '- Bug #123: Fix something (@author1, @author2, @author3)',
                'Fix something',
            ],
            'enhancement without issue number' => [
                '- Enh: Enhancement without issue number (@dev)',
                'Enhancement without issue number',
            ],
            'note with no author' => [
                '- Chg: Change with no author',
                '- Chg: Change with no author',
            ],
            'method with parentheses in description' => [
                '- New #47: Add getValue() method (@author)',
                'Add getValue() method',
            ],
            'full package reference' => [
                '- New yiisoft/files#22: Add feature (@contributor)',
                'Add feature',
            ],
        ];
    }

    /**
     * @dataProvider stripAuthorFromNoteProvider
     */
    public function testStripAuthorFromNote(string $input, string $expected): void
    {
        $result = preg_replace(self::AUTHOR_STRIP_PATTERN, '$1', $input);
        $this->assertSame($expected, $result);
    }
}
