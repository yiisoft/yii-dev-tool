<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace Production\Config;

function count($array)
{
    $i = 0;

    /** @noinspection PhpUnusedLocalVariableInspection */
    foreach ($array as $item) {
        $i++;
    }

    return $i;
}

class Config extends \Production\NonPSRNamespace\Config
{
    private int $size;
    private int $readTime;

    public function __construct(private array $data = [])
    {
        $this->size = count($data);

        /** @noinspection PhpFullyQualifiedNameUsageInspection */
        $this->readTime = \time();
    }
}
