<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace Custom\Storage;

class VarStorage extends \Custom\NonPSRNamespace\VarStorage
{
    /** @noinspection PhpUnusedPrivateMethodInspection */
    private function getCurrentTime()
    {
        /** @noinspection PhpFullyQualifiedNameUsageInspection */
        return \time();
    }
}
