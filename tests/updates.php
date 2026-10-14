<?php
declare(strict_types=1);
require __DIR__ . '/wp-doubles.php';
require dirname(__DIR__) . '/autoload.php';
use JT\WPCare\Modules\Updates\Catalog;
use JT\WPCare\Modules\Updates\Runner;
use JT\WPCare\Modules\Updates\Module;
use JT\WPCare\Modules\Updates\Cleanup;
function update_option($key, $value, $autoload = false) { if (empty($GLOBALS['save_fail'])) { $GLOBALS['options'][$key] = $value; } }
function get_filesystem_method($args = [], $context = '') { return 'direct'; }
function delete_plugins($plugins) { foreach ($plugins as $plugin) { unset($GLOBALS['plugins'][$plugin]); } return true; }
function wp_clean_plugins_cache($clear = true) {}
function verify($ok, $why) { if (!$ok) { throw new RuntimeException($why); } }
function reject(callable $call) { try { $call(); } catch (DomainException $e) { return; } throw new RuntimeException('Expected rejection'); }
$offers = [];
foreach (['plugin:ordinary/a.php', 'theme:parent', 'plugin:woocommerce/woocommerce.php'] as $key) {
    [$kind, $id] = explode(':', $key, 2);
    $offers[$key] = ['kind' => $kind, 'id' => $id, 'from' => '1.0', 'to' => '2.0', 'name' => $id, 'fingerprint' => hash('sha256', $key), 'critical' => $kind === 'theme' || str_contains($id, 'woocommerce'), 'blocked' => ''];
}
$selected = array_map(fn($r) => $r['fingerprint'], $offers);
$approved = array_fill_keys(array_keys($offers), 'yes');
$rows = Catalog::select(array_reverse($selected, true), $approved, $offers);
verify($rows[0]['kind'] === 'plugin' && $rows[1]['kind'] === 'theme' && $rows[2]['id'] === 'woocommerce/woocommerce.php', 'Woo last');
reject(fn() => Catalog::select($selected, [], $offers));
$changed = $offers; $changed[array_key_first($offers)]['fingerprint'] = 'changed';
reject(fn() => Catalog::select($selected, $approved, $changed));
$blocked = $offers; $blocked[array_key_first($offers)]['blocked'] = 'child theme';
reject(fn() => Catalog::select($selected, $approved, $blocked));
reject(fn() => Catalog::select([], [], []));
$GLOBALS['plugins'] = [
    'unused/unused.php' => ['Name' => 'Unused', 'Version' => '1.0', 'RequiresPlugins' => ''],
    'library/library.php' => ['Name' => 'Library', 'Version' => '2.0', 'RequiresPlugins' => ''],
    'active/active.php' => ['Name' => 'Active', 'Version' => '3.0', 'RequiresPlugins' => 'library'],
    'jt-wp-care-installer/jt-wp-care-installer.php' => ['Name' => 'Installer', 'Version' => '0.4', 'RequiresPlugins' => ''],
];
$GLOBALS['active'] = ['active/active.php'];
$inactive = Catalog::inactivePlugins();
verify(isset($inactive['unused/unused.php']) && $inactive['unused/unused.php']['blocked'] === '', 'Unused inactive plugin can be approved');
verify(str_contains($inactive['library/library.php']['blocked'], 'Active'), 'Dependency of active plugin is protected');
verify($inactive['jt-wp-care-installer/jt-wp-care-installer.php']['blocked'] !== '', 'Care installer is protected');
$base = sys_get_temp_dir() . '/jt-updates-' . bin2hex(random_bytes(5)); mkdir($base . '/jt-wp-care', 0755, true); define('WPMU_PLUGIN_DIR', $base);
define('WP_PLUGIN_DIR', $base . '/plugins'); mkdir(WP_PLUGIN_DIR);
try {
    $plan = ['id' => 'test', 'by' => 7, 'created' => time(), 'state' => 'ready', 'index' => 0, 'items' => $rows, 'results' => []];
    Runner::save($plan); $runner = new Runner();
    reject(fn() => $runner->step('wrong', 0));
    reject(fn() => $runner->step('test', 1));
    $running = $plan; $running['state'] = 'running'; Runner::save($running);
    reject(fn() => $runner->step('test', 0));
    $expired = $plan; $expired['created'] = time() - 3601; Runner::save($expired);
    reject(fn() => $runner->step('test', 0));
    $foreign = $plan; $foreign['by'] = 8; Runner::save($foreign); reject(fn() => $runner->step('test', 0));
    $verifying = $plan; $verifying['state'] = 'verifying'; $verifying['index'] = 1;
    $verifying['results'] = [['name' => 'ordinary/a.php', 'from' => '1.0', 'to' => '2.0', 'state' => 'verifying', 'message' => 'pending']];
    Runner::save($verifying);
    $GLOBALS['options']['page_on_front'] = 10; $GLOBALS['page_ids'] = [10, 11, 12, 13, 14];
    foreach (['https://example.test/', 'https://example.test/page-10/', 'https://example.test/page-11/', 'https://example.test/page-12/', 'https://example.test/page-13/'] as $url) {
        $GLOBALS['http'][$url] = ['code' => 200, 'headers' => ['content-type' => 'text/html'], 'body' => '<html>ok</html>'];
    }
    $verified = $runner->verify('test', 1);
    verify($verified['state'] === 'ready' && $verified['results'][0]['state'] === 'done', 'Fresh-request verification releases next step');
    reject(fn() => $runner->verify('test', 1));
    $GLOBALS['save_fail'] = true; reject(fn() => Runner::save($plan)); $GLOBALS['save_fail'] = false;
    verify(isset(get_option(Runner::HISTORY)['test']), 'History exists separately from human checklist');
    $row = Catalog::inactivePlugins()['unused/unused.php'];
    $GLOBALS['options']['page_on_front'] = 10; $GLOBALS['page_ids'] = [10, 11, 12, 13];
    foreach (['https://example.test/', 'https://example.test/page-10/', 'https://example.test/page-11/', 'https://example.test/page-12/', 'https://example.test/page-13/'] as $url) {
        $GLOBALS['http'][$url] = ['code' => 200, 'headers' => ['content-type' => 'text/html'], 'body' => '<html>ok</html>'];
    }
    $removed = (new Cleanup())->remove($row['id'], $row['fingerprint']);
    verify(!isset($GLOBALS['plugins'][$row['id']]) && $removed['verification']['state'] === 'done', 'Approved inactive plugin removed and verified');
    reject(fn() => (new Cleanup())->remove('library/library.php', $inactive['library/library.php']['fingerprint']));
    // The lock prevents concurrent requests and leaves no persistent running process.
    $lock = fopen($base . '/jt-wp-care/.maintenance.lock', 'c'); flock($lock, LOCK_EX);
    reject(fn() => $runner->locked(fn() => true)); flock($lock, LOCK_UN); fclose($lock);
} finally { unlink($base . '/jt-wp-care/.maintenance.lock'); rmdir($base . '/jt-wp-care'); rmdir(WP_PLUGIN_DIR); rmdir($base); }
$GLOBALS['authorized'] = false;
try { Module::authorize(); throw new LogicException('Unauthorized'); } catch (RuntimeException $e) { verify(str_contains($e->getMessage(), '403'), 'Capability guard'); }
echo "Update ordering, approvals, stale plans, locking, history and access tests passed.\n";
