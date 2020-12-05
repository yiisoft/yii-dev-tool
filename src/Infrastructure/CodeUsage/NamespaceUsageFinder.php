<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Infrastructure\CodeUsage;

use InvalidArgumentException;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use RuntimeException;
use Symfony\Component\Finder\Finder;

class NamespaceUsageFinder
{
    /**
     * Names of environments as keys and an array of paths to the target files as a value.
     * @var array[]
     */
    private array $target = [];

    /**
     * @var CodeUsage[]|null
     */
    private ?array $usages = null;

    public function addTargetPaths(string $environment, array $paths, ?string $basePath = null): self
    {
        foreach ($paths as $path) {
            if (!is_string($path)) {
                throw new InvalidArgumentException('$paths must be an array of strings.');
            }
        }

        if ($basePath !== null) {
            foreach ($paths as &$path) {
                $path = rtrim($basePath, '/') . '/' . ltrim($path, '/');
            }
        }

        if (!array_key_exists($environment, $this->target)) {
            $this->target[$environment] = [];
        }

        $this->target[$environment] = array_merge($this->target[$environment], $paths);

        return $this;
    }

    /**
     * @return CodeUsage[]
     */
    public function getUsages(): array
    {
        if ($this->usages === null) {
            $this->find();
        }

        return $this->usages;
    }

    private function find(): void
    {
        $this->usages = [];

        foreach ($this->target as $environment => $paths) {
            $files = $this->getExistingPHPFilePaths($environment);
            $this->findNamespaceUsagesInFiles($files, $environment);
        }
    }

    private function getExistingPHPFilePaths(string $environment): array
    {
        $files = [];

        foreach ($this->target[$environment] as $absolutePath) {
            if (!file_exists($absolutePath)) {
                continue;
            }

            if (is_file($absolutePath)) {
                if (pathinfo($absolutePath, PATHINFO_EXTENSION) === 'php') {
                    $files[] = $absolutePath;
                }

                continue;
            }

            foreach ((new Finder())->in($absolutePath)->name('*.php') as $finderFile) {
                $files[] = $finderFile->getRealPath();
            }
        }

        return $files;
    }

    private function findNamespaceUsagesInFiles(array $files, string $environment): void
    {
        foreach ($files as $file) {
            $code = file_get_contents($file);

            if ($code === false) {
                throw new RuntimeException('Unable to open file ' . $file);
            }

            $this->findNamespaceUsagesInPhpCode($code, $environment);
        }
    }

    private function findNamespaceUsagesInPhpCode(string &$code, string $environment): void
    {
        $stmts = (new ParserFactory())->create(ParserFactory::PREFER_PHP7)->parse($code);
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor(new NamespaceUsageFinderNameResolver($this, $environment));
        $nodeTraverser->traverse($stmts);
    }

    public function registerNamespaceUsage(string $namespace, string $environment): void
    {
        if (!array_key_exists($namespace, $this->usages)) {
            $this->usages[$namespace] = new CodeUsage($namespace, $environment);
        } else {
            $this->usages[$namespace]->registerUsageInEnvironment($environment);
        }
    }
}
