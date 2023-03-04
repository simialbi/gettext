<?php
declare(strict_types=1);

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    $loader = require_once __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../../autoload.php')) {
    $loader = require_once __DIR__ . '/../../../autoload.php';
} else {
    echo 'You must set up the project dependencies';
    exit(1);
}

return $loader;
