<?php

use Production\Config\Config;

return new Config([
    'params' => [
        'key-1' => 'value-1',
        'key-2' => 'value-2',
    ],
    'objects' => [
        'spl-fixed-array' => new \Production\Spl\SplFixedArray(),
    ]
]);
