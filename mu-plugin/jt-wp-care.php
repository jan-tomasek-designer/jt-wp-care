<?php
/**
 * Plugin Name: JT WP Care Loader
 * Description: Stabilní MU loader pro verzovaná vydání JT WP Care.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Text Domain: jt-wp-care
 */

// Protocol 1. Keep this entry point parsable on PHP 7 so guards can run.
if (!defined('ABSPATH') || (defined('JT_WP_CARE_DISABLED') && JT_WP_CARE_DISABLED)) {
    return;
}
(function () {
    $notice = function ($message) {
        add_action('admin_notices', function () use ($message) {
            if (current_user_can('manage_options')) {
                echo '<div class="notice notice-warning"><p>' . esc_html($message) . '</p></div>';
            }
        });
    };
    if (version_compare(PHP_VERSION, '8.0', '<') || version_compare($GLOBALS['wp_version'] ?? '0', '6.0', '<')) {
        $notice('JT WP Care vyžaduje WordPress 6.0+ a PHP 8.0+.');
        return;
    }
    $base = __DIR__ . '/jt-wp-care';
    // Reject linked loader state / release roots rather than traversing outside the install.
    if (is_link($base) || is_link($base . '/releases')) {
        $notice('JT WP Care: nepodporované přesměrování instalační složky.');
        return;
    }
    $readJson = function ($file) {
        if (is_link($file) || !is_file($file) || !is_readable($file) || filesize($file) > 4096) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : null;
    };
    $validVersion = function ($value) {
        return is_string($value) && strlen($value) <= 80 && preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:-[a-zA-Z0-9]+(?:[.-][a-zA-Z0-9]+)*)?$/D', $value);
    };
    $state = $readJson($base . '/current.json');
    $pinned = defined('JT_WP_CARE_PINNED_RELEASE');
    if ($pinned) {
        // Operator recovery override. Never silently fall back from an explicit pin.
        $candidates = [JT_WP_CARE_PINNED_RELEASE];
    } elseif ($state && ($state['schema'] ?? null) === 1 && $validVersion($state['active'] ?? null)
        && (($state['previous'] ?? null) === null || $validVersion($state['previous']))) {
        $candidates = [$state['active'], $state['previous'] ?? null];
    } else {
        $notice('JT WP Care: chybí nebo je neplatný current.json. Zvolte ověřené vydání přes JT_WP_CARE_PINNED_RELEASE.');
        return;
    }
    foreach ($candidates as $index => $version) {
        if (!$validVersion($version)) { continue; }
        $directory = $base . '/releases/' . $version;
        if (is_link($directory) || !is_dir($directory)) { continue; }
        $meta = $readJson($directory . '/release.json');
        if (!$meta || ($meta['schema'] ?? null) !== 1 || ($meta['version'] ?? null) !== $version
            || ($meta['loader_protocol'] ?? null) !== 1 || ($meta['data_schema'] ?? null) !== 1) { continue; }
        $compatible = true;
        foreach (['php_min' => PHP_VERSION, 'wp_min' => $GLOBALS['wp_version']] as $key => $actual) {
            if (!isset($meta[$key]) || !is_string($meta[$key]) || !preg_match('/^[0-9]+\.[0-9]+(?:\.[0-9]+)?$/D', $meta[$key]) || version_compare($actual, $meta[$key], '<')) {
                $compatible = false;
            }
        }
        foreach (['bootstrap.php', 'autoload.php'] as $entry) {
            if (is_link($directory . '/' . $entry) || !is_file($directory . '/' . $entry) || !is_readable($directory . '/' . $entry)) { $compatible = false; }
        }
        if (!$compatible) { continue; }
        if ($index > 0) { $notice('JT WP Care: aktivní vydání nelze načíst; používá se předchozí verze ' . $version . '.'); }
        // Freeze one release root for this request. Runtime failures never load a second namespace.
        define('JT_WP_CARE_LOADED_RELEASE', $version);
        require_once $directory . '/bootstrap.php';
        return;
    }
    $notice('JT WP Care: není dostupné kompatibilní vydání. Ostatní části webu se načítají bez JT WP Care.');
})();
