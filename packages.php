<?php

$packages = [
    'yiisoft/di' => 'di',
	'yiisoft/core' => 'core',

];

if (file_exists($localFile = __DIR__ . '/packages.local.php')) {
	$packages = array_merge($packages, require $localFile);
}

return $packages;

