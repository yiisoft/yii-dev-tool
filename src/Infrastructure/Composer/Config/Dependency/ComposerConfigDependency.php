<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Infrastructure\Composer\Config\Dependency;

class ComposerConfigDependency
{
    /**
     * @see https://github.com/composer/composer/blob/f15b9c258e736e773941eeb0333b1702d990ea52/src/Composer/Repository/PlatformRepository.php#L31
     */
    private const PLATFORM_PACKAGE_REGEX = '{^(?:php(?:-64bit|-ipv6|-zts|-debug)?|hhvm|(?:ext|lib)-[a-z0-9](?:[_.-]?[a-z0-9]+)*|composer-(?:plugin|runtime)-api)$}iD';

    private string $packageName;
    private string $constraint;

    public function __construct(string $packageName, string $constraint)
    {
        $this->packageName = $packageName;
        $this->constraint = $constraint;
    }

    public function getPackageName(): string
    {
        return $this->packageName;
    }

    public function getConstraint(): string
    {
        return $this->constraint;
    }

    /**
     * @param string[] $flags
     * @return bool
     */
    public function constraintContainsAnyOfStabilityFlags(array $flags): bool
    {
        foreach ($flags as $flag) {
            if (strpos($this->constraint, $flag) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * The real prioritization algorithm is more complicated:
     * https://github.com/composer/composer/blob/ec9ca9e7398229d6162c0d5e8b64990175476335/src/Composer/Json/JsonManipulator.php#L110-L146
     *
     * We use here a simplified version.
     *
     * @return int Conditional dependency priority for sorting.
     */
    public function getPriority(): int
    {
        $name = $this->packageName;

        if ($name === 'php') {
            return 0;
        }

        if (strpos($name, 'ext-') === 0) {
            return 1;
        }

        return 2;
    }

    public function isPlatformRequirement(): bool
    {
        return (bool) preg_match(self::PLATFORM_PACKAGE_REGEX, $this->packageName);
    }
}
