<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Test\Infrastructure\CodeUsage;

use PHPUnit\Framework\TestCase;
use Yiisoft\YiiDevTool\Infrastructure\CodeUsage\CodeUsageEnvironment;
use Yiisoft\YiiDevTool\Infrastructure\CodeUsage\NamespaceUsageFinder;

final class NamespaceUsageFinderTest extends TestCase
{
    protected function getFixturePath(string $name)
    {
        return __DIR__ . '/Fixture/' . $name;
    }

    public function testGetUsages()
    {
        $namespaceUsages =
            (new NamespaceUsageFinder())
                ->addTargetPaths(CodeUsageEnvironment::PRODUCTION, [
                    'config/prod.php',
                    'src',
                ], $this->getFixturePath('Production'))
                ->addTargetPaths('custom-environment', [
                    'config/custom.php',
                    'lib',
                ], $this->getFixturePath('Custom'))
                ->getUsages();

        $expectedResults = [
            // Used in custom environment only
            '\\' . \Custom\NonPSRNamespace\VarStorage::class => ['custom-environment'],
            '\\' . \Custom\Storage\VarStorage::class => ['custom-environment'],
            '\\' . \SplStack::class => ['custom-environment'],

            // Used in production environment only
            '\\' . \Production\Config\Config::class => [CodeUsageEnvironment::PRODUCTION],
            '\\' . \Production\NonPSRNamespace\Config::class => [CodeUsageEnvironment::PRODUCTION],
            '\\' . \Production\Spl\SplFixedArray::class => [CodeUsageEnvironment::PRODUCTION],
            '\\' . \SplFixedArray::class => [CodeUsageEnvironment::PRODUCTION],

            // Used in both environments
            '\time' => [CodeUsageEnvironment::PRODUCTION, 'custom-environment'],
        ];

        foreach ($expectedResults as $expectedNamespace => $expectedEnvironments) {
            $this->assertArrayHasKey($expectedNamespace, $namespaceUsages);
            $this->assertSame($expectedEnvironments, $namespaceUsages[$expectedNamespace]->getEnvironments());
        }

        // Not used, replaced by local implementation
        $this->assertArrayNotHasKey('\count', $namespaceUsages);
    }
}
