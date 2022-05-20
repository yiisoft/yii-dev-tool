<?php

declare(strict_types=1);

use Custom\Storage\VarStorage;

return new VarStorage([
    'var-1' => 'value-1',
    'var-2' => new SplStack(),
]);
