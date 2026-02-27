<?php
declare(strict_types=1);

/**
 * PHPUnit Bootstrap File
 * * This script runs once before any tests are executed.
 * Its primary job is to register the PSR-4 autoloader so PHPUnit 
 * knows where to find the application classes (PhpBirthday\*).
 */

spl_autoload_register(function (string $class) {
    $prefix = 'PhpBirthday\\';
    $baseDir = __DIR__ . '/../src/';
    
    // Check if the requested class uses our namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    // Convert namespace separators to directory separators
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});
