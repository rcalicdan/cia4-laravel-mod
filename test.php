<?php
echo "=== APCu Status Check ===\n";

// Check if extension is loaded
echo "Extension loaded: " . (extension_loaded('apcu') ? 'YES' : 'NO') . "\n";

// Check if APCu is enabled
echo "APCu enabled: " . (ini_get('apc.enabled') ? 'YES' : 'NO') . "\n";

// Check functions
$functions = ['apcu_store', 'apcu_fetch', 'apcu_delete', 'apcu_clear_cache'];
foreach ($functions as $func) {
    echo "Function $func: " . (function_exists($func) ? 'Available' : 'Missing') . "\n";
}

if (extension_loaded('apcu')) {
    echo "\n=== APCu Configuration ===\n";
    echo "Version: " . phpversion('apcu') . "\n";
    echo "Shared memory size: " . ini_get('apc.shm_size') . "\n";
    echo "TTL: " . ini_get('apc.ttl') . "\n";
}
