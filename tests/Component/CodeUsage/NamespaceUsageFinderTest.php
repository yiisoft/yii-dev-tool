<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Test\Component\CodeUsage;

use Yiisoft\YiiDevTool\Component\CodeUsage\CodeUsageEnvironment;
use Yiisoft\YiiDevTool\Component\CodeUsage\NamespaceUsageFinder;
use PHPUnit\Framework\TestCase;

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
            '\Custom\NonPSRNamespace\VarStorage' => ['custom-environment'],
            '\Custom\Storage\VarStorage' => ['custom-environment'],
            '\SplStack' => ['custom-environment'],

            // Used in production environment only
            '\Production\Config\Config' => [CodeUsageEnvironment::PRODUCTION],
            '\Production\NonPSRNamespace\Config' => [CodeUsageEnvironment::PRODUCTION],
            '\Production\Spl\SplFixedArray' => [CodeUsageEnvironment::PRODUCTION],
            '\SplFixedArray' => [CodeUsageEnvironment::PRODUCTION],

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
