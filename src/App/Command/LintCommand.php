<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Runner;
use PHP_CodeSniffer\Util\Timing;
use ReflectionClass;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;

final class LintCommand extends PackageCommand
{
    protected function configure()
    {
        $this
            ->setName('lint')
            ->setDescription('Check packages according to PSR-12 standard');

        parent::configure();
    }

    protected function getMessageWhenNothingHasBeenOutput(): ?string
    {
        return '<success>✔ No problems found</success>';
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, 'Linting package {package}');

        $pharFile = 'phar://' . $this->getApplication()->getRootDir() . 'devtool.phar';
        if (is_file($pharFile . '/vendor/squizlabs/php_codesniffer/autoload.php') === true) {
            include_once $pharFile . '/vendor/squizlabs/php_codesniffer/autoload.php';
        } else {
            $io->error('Failed to load autoload file php_codesniffer vendor package.');
            exit(1);
        }

        $_SERVER['argv'] = [
            '',
            $package->getPath(),
            $io->hasColorSupport() ? '--colors' : '--no-colors',
            '--standard=PSR12',
            '--ignore=*/vendor/*,*/docs/*',
        ];

        $reflection = new ReflectionClass(Config::class);
        $reflection->setStaticPropertyValue('overriddenDefaults', []);

        $reflection = new ReflectionClass(Timing::class);
        $reflection->setStaticPropertyValue('printed', false);

        ob_start();
        $runner = new Runner();
        $exitCode = $runner->runPHPCS();
        $result = ob_get_contents();
        ob_clean();
        if ($exitCode > 0) {
            $io->important()->info($result);
        } else {
            $io->success('✔ No problems found.');
        }
    }
}
