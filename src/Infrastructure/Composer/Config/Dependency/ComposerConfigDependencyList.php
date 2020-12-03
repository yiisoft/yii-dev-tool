<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Infrastructure\Composer\Config\Dependency;

use InvalidArgumentException;
use RuntimeException;

class ComposerConfigDependencyList
{
    /**
     * @var ComposerConfigDependency[]
     */
    private array $dependencies = [];

    public function __construct(array $dependenciesAsArray = null)
    {
        if ($dependenciesAsArray !== null) {
            foreach ($dependenciesAsArray as $packageName => $constraint) {
                $this->dependencies[$packageName] = new ComposerConfigDependency($packageName, $constraint);
            }
        }
    }

    public function asArray(): array
    {
        $result = [];

        foreach ($this->dependencies as $dependency) {
            $result[$dependency->getPackageName()] = $dependency->getConstraint();
        }

        return $result;
    }

    public function isEmpty()
    {
        return count($this->dependencies) === 0;
    }

    public function isEqualTo(ComposerConfigDependencyList $otherList): bool
    {
        return $this->asArray() === $otherList->asArray();
    }

    public function hasDependency(string $packageName): bool
    {
        return array_key_exists($packageName, $this->dependencies);
    }

    public function getDependency(string $packageName): ComposerConfigDependency
    {
        if (!$this->hasDependency($packageName)) {
            throw new RuntimeException('A package with this name does not exist in the list.');
        }

        return $this->dependencies[$packageName];
    }

    /**
     * @return ComposerConfigDependency[]
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function addDependency(string $packageName, string $constraint): self
    {
        if ($this->hasDependency($packageName)) {
            throw new RuntimeException('A package with this name already exists in the list.');
        }

        $this->dependencies[$packageName] = new ComposerConfigDependency($packageName, $constraint);

        return $this;
    }

    public function removeDependency(string $packageNameToRemove): self
    {
        unset($this->dependencies[$packageNameToRemove]);

        return $this;
    }

    public function removeDependencies(array $packageNamesToRemove): self
    {
        foreach ($packageNamesToRemove as $packageNameToRemove) {
            if (!is_string($packageNameToRemove)) {
                throw new InvalidArgumentException('Package names must be strings.');
            }
        }

        foreach ($packageNamesToRemove as $packageNameToRemove) {
            $this->removeDependency($packageNameToRemove);
        }

        return $this;
    }

    public function sort(): self
    {
        uasort($this->dependencies, function ($a, $b) {
            /* @var $a ComposerConfigDependency */
            /* @var $b ComposerConfigDependency */
            if ($a->getPriority() === $b->getPriority()) {
                return strnatcmp($a->getPackageName(), $b->getPackageName());
            }

            return $a->getPriority() - $b->getPriority();
        });

        return $this;
    }
}
