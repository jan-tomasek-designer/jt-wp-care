<?php
declare(strict_types=1);
require __DIR__ . '/wp-doubles.php';
require dirname(__DIR__) . '/autoload.php';
use JT\WPCare\Modules\WooCommerce\Inspector;
use JT\WPCare\Modules\WooCommerce\Module;
function check(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } }
class WC_Install { public static function needs_db_update() { return $GLOBALS['needs'] ?? false; } }
function did_action($hook) { return $GLOBALS['ready'] ?? false; }
function as_get_scheduled_actions($args, $format) {
    check($args['per_page'] === 1 && $format === 'ids', 'Bounded IDs only');
    check(!isset($args['args']), 'No customer arguments');
    $GLOBALS['queries'][] = $args;
    if (!empty($GLOBALS['queue_error'])) { throw new RuntimeException('private database error'); }
    if (!empty($GLOBALS['bad_result'])) { return false; }
    $key = ($args['group'] ?? 'site') . ':' . $args['status'];
    return !empty($GLOBALS['found'][$key]) ? [1] : [];
}
$i = new Inspector(); $now = 1800000000;
check($i->collect($now)['wc_database']['state'] === 'na', 'Woo absent');
define('WC_VERSION', '10.6.1');
check($i->collect($now)['wc_database']['state'] === 'unknown', 'Missing DB metadata');
$GLOBALS['options']['woocommerce_db_version'] = '10.6.1';
$GLOBALS['needs'] = true;
check($i->collect($now)['wc_database']['state'] === 'problem', 'Native database requirement');
$GLOBALS['needs'] = false;
check($i->collect($now)['wc_database']['state'] === 'done', 'Native WooCommerce state proves no migration is requested');
check(empty($GLOBALS['queries']), 'Uninitialized queue not queried');
$GLOBALS['ready'] = true;
$r = $i->collect($now);
check($r['wc_queue']['state'] === 'unknown', 'Empty queue cannot prove cron');
check(count($GLOBALS['queries']) === 5, 'Five bounded probes');
check($GLOBALS['queries'][1]['date'] === $now - 3600 && $GLOBALS['queries'][1]['date_compare'] === '<', 'One hour threshold');
foreach (['site:failed', 'site:pending'] as $key) {
    $GLOBALS['found'] = [$key => true];
    check($i->collect($now)['wc_queue']['state'] === 'problem', 'Failed/late queue detected');
}
foreach (['pending', 'in-progress', 'failed'] as $status) {
    $GLOBALS['found'] = ['woocommerce-db-updates:' . $status => true];
    check($i->collect($now)['wc_database']['state'] === 'problem', 'Migration queue detected ' . $status);
}
$GLOBALS['queue_error'] = true;
check($i->collect($now)['wc_queue']['state'] === 'unknown', 'Queue exception contained');
$GLOBALS['queue_error'] = false; $GLOBALS['bad_result'] = true;
check($i->collect($now)['wc_queue']['state'] === 'unknown', 'Invalid query result');
$GLOBALS['bad_result'] = false;
(new Module())->register();
$r = apply_filters('jt_wp_care_automatic_results', ['php' => ['state' => 'done']]);
check(isset($r['php'], $r['wc_queue'], $r['wc_database']), 'Integration preserves other checks');
define('JT_WP_CARE_DISABLED_MODULES', ['woocommerce']);
$r = \JT\WPCare\Modules\Automatic\Evaluation::results(['at' => gmdate('c'), 'automatic' => $r]);
check(!isset($r['wc_queue'], $r['wc_database']) && isset($r['php']), 'Disabled module hides saved findings');
echo "WooCommerce read-only diagnostics passed.\n";
