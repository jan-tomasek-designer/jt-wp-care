<?php
declare(strict_types=1);
require __DIR__ . '/wp-doubles.php';
require dirname(__DIR__) . '/autoload.php';
use JT\WPCare\Modules\Updates\Catalog;
use JT\WPCare\Modules\Updates\Runner;
use JT\WPCare\Modules\Updates\Module;
function update_option($key, $value, $autoload = false) { if (empty($GLOBALS['save_fail'])) { $GLOBALS['options'][$key] = $value; } }
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
$base = sys_get_temp_dir() . '/jt-updates-' . bin2hex(random_bytes(5)); mkdir($base . '/jt-wp-care', 0755, true); define('WPMU_PLUGIN_DIR', $base);
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
    $GLOBALS['save_fail'] = true; reject(fn() => Runner::save($plan)); $GLOBALS['save_fail'] = false;
    verify(isset(get_option(Runner::HISTORY)['test']), 'History exists separately from human checklist');
    // The lock prevents concurrent requests and leaves no persistent running process.
    $lock = fopen($base . '/jt-wp-care/.maintenance.lock', 'c'); flock($lock, LOCK_EX);
    reject(fn() => $runner->locked(fn() => true)); flock($lock, LOCK_UN); fclose($lock);
} finally { unlink($base . '/jt-wp-care/.maintenance.lock'); rmdir($base . '/jt-wp-care'); rmdir($base); }
$GLOBALS['authorized'] = false;
try { Module::authorize(); throw new LogicException('Unauthorized'); } catch (RuntimeException $e) { verify(str_contains($e->getMessage(), '403'), 'Capability guard'); }
echo "Update ordering, approvals, stale plans, locking, history and access tests passed.\n";
