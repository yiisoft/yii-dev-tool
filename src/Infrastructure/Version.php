<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Infrastructure;

final class Version
{
    public const TYPE_MAJOR = 'Major - Incompatible API changes.';
    public const TYPE_MINOR = 'Minor - Add functionality (backwards-compatible).';
    public const TYPE_PATCH = 'Patch - Bug fixes (backwards-compatible).';

    public const TYPES = [self::TYPE_PATCH, self::TYPE_MINOR, self::TYPE_MAJOR];

    private string $version;

    public function __construct(string $version)
    {
        $this->version = $version;
    }

    public function __toString(): string
    {
        return $this->asString();
    }

    public function asString(): string
    {
        return $this->version;
    }

    public function getNext(string $type): Version
    {
        if ($this->version === '') {
            return new Version('1.0.0');
        }

        $parts = explode('.', $this->version);
        switch ($type) {
            case self::TYPE_MAJOR:
                $parts[0]++;
                $parts[1] = 0;
                $parts[2] = 0;
                break;
            case self::TYPE_MINOR:
                $parts[1]++;
                $parts[2] = 0;
                break;
            case self::TYPE_PATCH:
                $parts[2]++;
                break;
            default:
                throw new \RuntimeException('Unknown version type.');
        }
        return new self(implode('.', $parts));
    }
}
