<?php


namespace Yiisoft\YiiDevTool\Component;


final class Version
{
    public const TYPE_MAJOR = 'major';
    public const TYPE_MINOR = 'minor';
    public const TYPE_PATCH = 'patch';

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
