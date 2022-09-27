<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace Custom\NonPSRNamespace;

class VarStorage
{
    public function __construct(private array $vars = [])
    {
    }
}
