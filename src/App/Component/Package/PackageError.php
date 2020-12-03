<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Component\Package;

class PackageError
{
    private Package $package;
    private string $message;
    private string $during;

    public function __construct(Package $package, string $message, string $during)
    {
        $this->package = $package;
        $this->message = $message;
        $this->during = $during;
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
