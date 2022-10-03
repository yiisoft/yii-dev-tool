<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Component\Package;

class PackageError
{
    public function __construct(private Package $package, private string $message, private string $during)
    {
    }

    public function getPackage(): Package
    {
        return $this->package;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getDuring(): string
    {
        return $this->during;
    }
}
