<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Test\App\Command\Release;

use PHPUnit\Framework\TestCase;
use Yiisoft\YiiDevTool\App\Command\Release\ReleaseDescription;
use Yiisoft\YiiDevTool\Infrastructure\Version;

final class ReleaseDescriptionTest extends TestCase
{
    public function testGetBodyAddsFullChangelogCompareLink(): void
    {
        $description = new ReleaseDescription();

        $expectedBody = <<<BODY
        - Bug #1: Fixed issue (@samdark)

        [Full changelog](https://github.com/yiisoft/html/compare/3.10.0...3.11.0)
        BODY;

        $this->assertSame(
            $expectedBody,
            $description->getBody(
                'yiisoft/html',
                new Version('3.10.0'),
                new Version('3.11.0'),
                ['- Bug #1: Fixed issue (@samdark)']
            )
        );
    }

    public function testGetBodyDoesNotAddFullChangelogLinkWithoutPreviousVersion(): void
    {
        $description = new ReleaseDescription();

        $this->assertSame(
            '- Initial release.',
            $description->getBody(
                'yiisoft/html',
                new Version(''),
                new Version('1.0.0'),
                ['- Initial release.']
            )
        );
    }

    public function testGetBodyAddsUpgradeNotesLinkForMajorReleaseWhenUpgradeNotesExist(): void
    {
        $description = new ReleaseDescription();

        $expectedBody = <<<BODY
        - Enh #1: Removed deprecated API (@samdark)

        [Full changelog](https://github.com/yiisoft/html/compare/3.10.0...4.0.0)

        See [UPGRADE.md](https://github.com/yiisoft/html/blob/4.0.0/UPGRADE.md) for upgrade notes.
        BODY;

        $this->assertSame(
            $expectedBody,
            $description->getBody(
                'yiisoft/html',
                new Version('3.10.0'),
                new Version('4.0.0'),
                ['- Enh #1: Removed deprecated API (@samdark)'],
                true
            )
        );
    }

    public function testGetBodyDoesNotAddUpgradeNotesLinkForMinorRelease(): void
    {
        $description = new ReleaseDescription();

        $expectedBody = <<<BODY
        - Enh #1: Added API (@samdark)

        [Full changelog](https://github.com/yiisoft/html/compare/3.10.0...3.11.0)
        BODY;

        $this->assertSame(
            $expectedBody,
            $description->getBody(
                'yiisoft/html',
                new Version('3.10.0'),
                new Version('3.11.0'),
                ['- Enh #1: Added API (@samdark)'],
                true
            )
        );
    }

    public function testGetBodyDoesNotAddUpgradeNotesLinkWhenUpgradeNotesDoNotExist(): void
    {
        $description = new ReleaseDescription();

        $expectedBody = <<<BODY
        - Enh #1: Removed deprecated API (@samdark)

        [Full changelog](https://github.com/yiisoft/html/compare/3.10.0...4.0.0)
        BODY;

        $this->assertSame(
            $expectedBody,
            $description->getBody(
                'yiisoft/html',
                new Version('3.10.0'),
                new Version('4.0.0'),
                ['- Enh #1: Removed deprecated API (@samdark)']
            )
        );
    }
}
