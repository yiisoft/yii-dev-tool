<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Infrastructure\CodeUsage;

use InvalidArgumentException;

class CodeUsage
{
    private string $identifier;

    /**
     * @var string[]
     */
    private array $environments = [];

    /**
     * @param string $identifier Unique identifier of code usage: namespace, package name, etc.
     * @param string[] $environments Environment in which the code is used.
     */
    public function __construct(string $identifier, array $environments = [])
    {
        foreach ($environments as $environment) {
            if (!is_string($environment)) {
                throw new InvalidArgumentException('Each environment must be a string.');
            }
        }

        $this->identifier = $identifier;
        $this->environments = $environments;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @return string[]
     */
    public function getEnvironments(): array
    {
        return $this->environments;
    }

    public function registerUsageInEnvironment(string $environment): void
    {
        if (!in_array($environment, $this->environments, true)) {
            $this->environments[] = $environment;
        }
    }

    /**
     * @param string[] $environments
     */
    public function registerUsageInEnvironments(array $environments): void
    {
        foreach ($environments as $environment) {
            if (!is_string($environment)) {
                throw new InvalidArgumentException('Each environment must be a string.');
            }

            $this->registerUsageInEnvironment($environment);
        }
    }

    public function usedInEnvironment(string $environment): bool
    {
        return in_array($environment, $this->environments, true);
    }

    public function usedOnlyInSpecifiedEnvironment(string $environment): bool
    {
        if (count($this->environments) !== 1) {
            return false;
        }

        return in_array($environment, $this->environments, true);
    }

    public function used(): bool
    {
        return count($this->environments) > 0;
    }
}
