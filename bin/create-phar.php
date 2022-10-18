<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

require __DIR__ . '/../vendor/autoload.php';

$pharFile = 'devtool.phar';
if (file_exists($pharFile)) {
    unlink($pharFile);
}

$phar = new Phar($pharFile, 0, $pharFile);
$phar->setSignatureAlgorithm(Phar::SHA512);

$phar->startBuffering();

$finderSort = static function ($a, $b): int {
    return strcmp(str_replace('\\', '/', $a->getRealPath()), str_replace('\\', '/', $b->getRealPath()));
};

// Add Dev Tool sources
$finder = new Finder();
$finder->files()
    ->ignoreVCS(true)
    ->name('*.php')
    ->in(__DIR__.'/../src')
    ->sort($finderSort);

foreach ($finder as $file) {
    $phar->addFromString(getRelativeFilePath($file), $file->getContents());
}

// Add vendor files
$finder = new Finder();
$finder->files()
    ->ignoreVCS(true)
    ->notPath('/\/(composer\.(json|lock)|[A-Z]+\.md(?:own)?|\.gitignore|appveyor.yml|phpunit\.xml\.dist|phpstan\.neon\.dist|phpstan-config\.neon|phpstan-baseline\.neon)$/')
    ->notPath('/bin\/(jsonlint|validate-json|simple-phpunit|phpstan|phpstan\.phar)(\.bat)?$/')
    ->notPath('composer/installed.json')
    ->notPath('composer/LICENSE')
    ->notPath([
        __DIR__ . '/../../vendor/symfony/console/Resources/bin/hiddeninput.exe',
        __DIR__ . '/../../vendor/symfony/console/Resources/completion.bash',
    ])
    ->exclude('Tests')
    ->exclude('tests')
    ->exclude('docs')
    ->in(__DIR__.'/../vendor/')
    ->sort($finderSort)
;

foreach ($finder as $file) {
    $phar->addFromString(getRelativeFilePath($file), $file->getContents());
}

$content = file_get_contents(__DIR__.'/dev-tool');
$content = str_replace('{^#!/usr/bin/env php\s*}', '', $content);
$phar->addFromString('bin/dev-tool', $content);

// Stubs
$phar->setStub(getStub());

$phar->stopBuffering();

function getStub(): string
{
    $stub = <<<'EOF'
#!/usr/bin/env php
<?php

if (!class_exists('Phar')) {
    echo 'PHP\'s phar extension is missing. Dev tool requires it to run. Enable the extension or recompile php without --disable-phar then try again.' . PHP_EOL;
    exit(1);
}

Phar::mapPhar('devtool.phar');

EOF;


    return $stub . <<<'EOF'
require 'phar://devtool.phar/bin/dev-tool';

__HALT_COMPILER();
EOF;
}

unset($phar);

function getRelativeFilePath(SplFileInfo $file): string
{
    $realPath = $file->getRealPath();
    $pathPrefix = dirname(__DIR__).DIRECTORY_SEPARATOR;

    $pos = strpos($realPath, $pathPrefix);
    $relativePath = ($pos !== false) ? substr_replace($realPath, '', $pos, strlen($pathPrefix)) : $realPath;

    return str_replace('\\', '/', $relativePath);
}
