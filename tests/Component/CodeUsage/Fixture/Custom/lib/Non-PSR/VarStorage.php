<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace Custom\NonPSRNamespace;

class VarStorage
{
    private array $vars;

    public function __construct(array $vars = [])
    {
        $this->vars = $vars;
    }
}
