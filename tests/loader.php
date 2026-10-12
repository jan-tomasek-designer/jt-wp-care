<?php
declare(strict_types=1);
// Run each scenario in a fresh PHP process after tools/build.php.
$mode = $argv[1] ?? 'admin';
define('ABSPATH', __DIR__ . '/');
$GLOBALS['wp_version'] = $mode === 'old-wp' ? '5.9' : '6.0';
if ($mode === 'disabled') { define('JT_WP_CARE_DISABLED', true); }
if ($mode === 'modules-disabled') { define('JT_WP_CARE_DISABLED_MODULES', ['dashboard', 'checklist', 'security', 'reports']); }
$hooks = [];
function is_admin() { global $mode; return $mode !== 'frontend'; }
function is_multisite() { global $mode; return $mode === 'multisite'; }
function add_action($hook, $callback, $priority = 10) { global $hooks; $hooks[$hook][] = $callback; }
require dirname(__DIR__) . '/dist/mu-plugins/jt-wp-care.php';
foreach ($hooks['plugins_loaded'] ?? [] as $callback) { $callback(); }
$handlers = array_filter(array_keys($hooks), fn($key) => str_starts_with($key, 'admin_post_'));
if ($mode === 'admin') {
    if (count($handlers) !== 4 || !isset($hooks['admin_menu'])) { throw new RuntimeException('Admin registration failed'); }
} elseif ($handlers !== []) { throw new RuntimeException('Disabled environment registered handlers'); }
if ($mode === 'disabled' && $hooks !== []) { throw new RuntimeException('Kill switch registered hooks'); }
if (in_array($mode, ['old-wp', 'multisite'], true) && !isset($hooks['admin_notices'])) { throw new RuntimeException('Missing compatibility notice'); }
echo "Loader passed: {$mode}\n";
