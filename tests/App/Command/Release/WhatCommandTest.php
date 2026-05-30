<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Test\App\Command\Release;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Yiisoft\YiiDevTool\App\YiiDevToolApplication;

final class WhatCommandTest extends TestCase
{
    private string $rootDir;
    private string $packagesRootDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rootDir = sys_get_temp_dir() . '/yii-dev-tool-what-command-' . bin2hex(random_bytes(8));
        $this->packagesRootDir = sys_get_temp_dir() . '/yii-dev-tool-what-command-packages-' . bin2hex(random_bytes(8));

        (new Filesystem())->mkdir([$this->rootDir, $this->packagesRootDir]);

        file_put_contents($this->rootDir . '/owner-packages.php', "<?php\n\nreturn 'yiisoft';\n");
        file_put_contents(
            $this->rootDir . '/packages.php',
            "<?php\n\nreturn [\n    'demo' => true,\n    'input-http' => true,\n    'request-model' => true,\n    'validator' => true,\n];\n"
        );

        (new Filesystem())->mkdir($this->packagesRootDir . '/demo');

        $this->createPackage('input-http', ['yiisoft/request-model' => '^1.0']);
        $this->createPackage('request-model', ['yiisoft/validator' => '^1.0']);
        $this->createPackage('validator');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove([$this->rootDir, $this->packagesRootDir]);

        parent::tearDown();
    }

    public function testListsOnlyOutgoingPackages(): void
    {
        $application = (new YiiDevToolApplication(['packagesRootDir' => $this->packagesRootDir]))
            ->setRootDir($this->rootDir);
        $command = $application->find('release:what');

        $commandTester = new CommandTester($command);
        $this->assertSame(0, $commandTester->execute([]));

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Out packages', $output);
        $this->assertMatchesRegularExpression(
            '/\| yiisoft\/request-model\s+\| 1\s+\| 1\s+\| validator\s+\|/',
            $output
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\| yiisoft\/request-model\s+\| 1\s+\| 1\s+\|[^\n]*input-http/',
            $output
        );
    }

    private function createPackage(string $name, array $require = []): void
    {
        $packageDir = $this->packagesRootDir . '/' . $name;
        (new Filesystem())->mkdir($packageDir);

        $composer = [
            'name' => 'yiisoft/' . $name,
            'require' => $require,
        ];

        file_put_contents(
            $packageDir . '/composer.json',
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );

        (new Process(['git', 'init', '--quiet'], $packageDir))->mustRun();
    }
}
