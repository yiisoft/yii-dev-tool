<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Test\App\Command\Release;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Yiisoft\YiiDevTool\App\Command\Release\WhatCommand;
use Yiisoft\YiiDevTool\App\YiiDevToolApplication;

final class WhatCommandTest extends TestCase
{
    private string $rootDir;
    private string $packagesRootDir;
    private string $packageDir;
    private FakeGitHubReleaseInspector $gitHub;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = bin2hex(random_bytes(8));
        $this->rootDir = sys_get_temp_dir() . '/yii-dev-tool-release-what-' . $suffix;
        $this->packagesRootDir = sys_get_temp_dir() . '/yii-dev-tool-release-what-packages-' . $suffix;
        $this->packageDir = $this->packagesRootDir . '/demo';
        (new Filesystem())->mkdir([$this->rootDir, $this->packageDir]);

        file_put_contents($this->rootDir . '/owner-packages.php', "<?php\n\nreturn 'yiisoft';\n");
        file_put_contents($this->rootDir . '/packages.php', "<?php\n\nreturn ['demo' => true];\n");
        file_put_contents($this->packageDir . '/composer.json', <<<'JSON'
        {
            "name": "yiisoft/demo",
            "require": {"php": "^8.1"}
        }
        JSON);
        file_put_contents($this->packageDir . '/CHANGELOG.md', <<<'MARKDOWN'
        # Demo Change Log

        ## 1.0.1 under development

        - Bug #12: Fix the demo

        ## 1.0.0 January 01, 2026

        - Initial release.
        MARKDOWN);

        $this->git('init', '--quiet');
        $this->git('config', 'user.email', 'test@example.com');
        $this->git('config', 'user.name', 'Test');
        $this->git('config', 'commit.gpgsign', 'false');
        $this->git('add', '.');
        $this->git('commit', '--quiet', '-m', 'Initial release');
        $this->git('tag', '1.0.0');
        file_put_contents($this->packageDir . '/change.txt', "change\n");
        $this->git('add', '.');
        $this->git('commit', '--quiet', '-m', 'Fix the demo');

        $this->gitHub = new FakeGitHubReleaseInspector($this->git('rev-parse', 'HEAD'));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove([$this->rootDir, $this->packagesRootDir]);

        parent::tearDown();
    }

    public function testListsReadyPackageWithSummary(): void
    {
        $tester = $this->createCommandTester();

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Packages to release', $tester->getDisplay());
        self::assertStringContainsString('yiisoft/demo', $tester->getDisplay());
        self::assertStringContainsString('1.0.1', $tester->getDisplay());
        self::assertStringContainsString('Bug #12: Fix the demo', $tester->getDisplay());
    }

    public function testListsBlockedPackageAndReturnsFailure(): void
    {
        $this->gitHub->checks[0]['conclusion'] = 'failure';
        $this->gitHub->issueStates[12] = 'open';
        $tester = $this->createCommandTester();

        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('Blocked packages', $tester->getDisplay());
        self::assertStringContainsString('GitHub check "phpunit" concluded with failure.', $tester->getDisplay());
        self::assertStringContainsString('Issue #12 is still open.', $tester->getDisplay());
    }

    public function testOmitsPackageWithoutCommitsAfterTag(): void
    {
        $this->git('tag', '-f', '1.0.1');
        $tester = $this->createCommandTester();

        self::assertSame(0, $tester->execute([]));
        self::assertStringNotContainsString('yiisoft/demo', $tester->getDisplay());
    }

    public function testTreatsRepositoryWithoutTagsAsInitialReleaseCandidate(): void
    {
        $this->git('tag', '--delete', '1.0.0');
        $tester = $this->createCommandTester();

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Packages to release', $tester->getDisplay());
    }

    public function testDirtyWorkingTreeBlocksRelease(): void
    {
        file_put_contents($this->packageDir . '/uncommitted.txt', "change\n");
        $tester = $this->createCommandTester();

        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('Working tree is not clean.', $tester->getDisplay());
    }

    private function createCommandTester(): CommandTester
    {
        $application = (new YiiDevToolApplication(['packagesRootDir' => $this->packagesRootDir]))
            ->setRootDir($this->rootDir);
        $application->add(new WhatCommand($this->gitHub));

        return new CommandTester($application->find('release:what'));
    }

    private function git(string ...$arguments): string
    {
        return trim((new Process(['git', ...$arguments], $this->packageDir))->mustRun()->getOutput());
    }
}
