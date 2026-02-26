<?php
/**
 * CoCart PHP SDK Autoloader
 * 
 * Simple PSR-4 autoloader for those not using Composer.
 * 
 * Usage:
 *   require_once 'path/to/cocart-sdk/autoload.php';
 * 
 * @package CoCart\SDK
 */

// Load the main CoCart class (global namespace)
require_once __DIR__ . '/src/CoCart.php';

spl_autoload_register(function ($class) {
    // CoCart namespace prefix
    $prefix = 'CoCart\\';
    
    // Base directory for the namespace
    $baseDir = __DIR__ . '/src/';
    
    // Check if the class uses the namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // Not a CoCart class, let another autoloader handle it
        return;
    }
    
    // Get the relative class name
    $relativeClass = substr($class, $len);
    
    // Replace namespace separators with directory separators
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});
