<?php
declare(strict_types=1);

// One namespace, one directory; no runtime Composer installation is required.
spl_autoload_register(static function (string $class): void {
    $prefix = 'JT\\WPCare\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    if (!preg_match('/^[A-Za-z0-9_\\\\]+$/D', $relative)) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
