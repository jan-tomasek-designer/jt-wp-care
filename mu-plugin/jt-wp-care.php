<?php
/**
 * Plugin Name: JT WP Care
 * Description: Interní modulární diagnostika a údržba WordPressu.
 * Version: 0.2.0-alpha
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Text Domain: jt-wp-care
 */

// Keep the loader readable by older PHP so the version guard can run.
if (!defined('ABSPATH')) {
    return;
}
if (defined('JT_WP_CARE_DISABLED') && JT_WP_CARE_DISABLED) {
    return;
}
if (version_compare(PHP_VERSION, '8.0', '<') || version_compare($GLOBALS['wp_version'] ?? '0', '6.0', '<')) {
    add_action('admin_notices', function () {
        if (current_user_can('manage_options')) {
            echo '<div class="notice notice-error"><p>JT WP Care vyžaduje WordPress 6.0+ a PHP 8.0+.</p></div>';
        }
    });
    return;
}
$jtCareBootstrap = __DIR__ . '/jt-wp-care/bootstrap.php';
if (!is_readable($jtCareBootstrap)) {
    add_action('admin_notices', function () {
        if (current_user_can('manage_options')) {
            echo '<div class="notice notice-error"><p>JT WP Care: chybí instalační soubory.</p></div>';
        }
    });
    return;
}
require_once $jtCareBootstrap;
unset($jtCareBootstrap);

