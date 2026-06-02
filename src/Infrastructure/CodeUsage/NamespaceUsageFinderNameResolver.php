<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Infrastructure\CodeUsage;

use PhpParser\ErrorHandler;
use PhpParser\Node;
use PhpParser\NodeVisitor\NameResolver;

class NamespaceUsageFinderNameResolver extends NameResolver
{
    public function __construct(
        protected NamespaceUsageFinder $namespaceUsageFinder,
        protected string $environment,
        ?ErrorHandler $errorHandler = null,
        array $options = []
    ) {
        parent::__construct($errorHandler, $options);
    }

    public function leaveNode(Node $node): void
    {
        if ($node instanceof Node\Name\FullyQualified) {
            $fullyQualifiedNamespace = $node->toCodeString();
            $this->namespaceUsageFinder->registerNamespaceUsage($fullyQualifiedNamespace, $this->environment);
        }
    }
}
