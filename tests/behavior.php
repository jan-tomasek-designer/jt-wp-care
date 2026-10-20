<?php
declare(strict_types=1);
require __DIR__ . '/wp-doubles.php';
require dirname(__DIR__) . '/autoload.php';
use JT\WPCare\Core\Access;
use JT\WPCare\Core\Store;
use JT\WPCare\Modules\Checklist\Catalog;
use JT\WPCare\Modules\Security\XmlRpcPolicy;
use JT\WPCare\Modules\Security\Collector;
use JT\WPCare\Modules\Reports\Formatter;

$count = 0;
function check(bool $condition, string $message): void { global $count; ++$count; if (!$condition) { throw new RuntimeException($message); } }
function rejects(callable $callback, string $fragment): void {
    try { $callback(); } catch (\Throwable $error) { check(str_contains($error->getMessage(), $fragment), 'Wrong rejection: ' . $error->getMessage()); return; }
    throw new RuntimeException('Expected rejection: ' . $fragment);
}
$input = [];
foreach (Catalog::items() as $id => $item) { $input[$id] = ['state' => 'unknown', 'note' => '']; }
$empty = Catalog::normalize($input, []);
check(count($empty) === count(Catalog::items()), 'All checklist items required.');
check($empty['backup']['state'] === 'unknown', 'Never assume a backup.');
$bad = $input; $bad['backup']['state'] = 'done';
rejects(fn() => Catalog::normalize($bad, []), 'důkaz');
$bad['backup']['note'] = 'DB + soubory, dokončení ověřeno';
$done = Catalog::normalize($bad, []);
check($done['backup']['by'] === 7 && $done['backup']['at'] !== '', 'Evidence attribution.');
check(Catalog::normalize($bad, $done)['backup'] === $done['backup'], 'Unchanged evidence keeps timestamp.');
$bad['backup']['state'] = 'attacker'; rejects(fn() => Catalog::normalize($bad, []), 'Neúplný');
$bad['backup']['state'] = 'done'; $bad['backup']['note'] = ['nested']; rejects(fn() => Catalog::normalize($bad, []), 'Neúplný');
$bad['backup']['note'] = str_repeat('x', 6001); rejects(fn() => Catalog::normalize($bad, []), 'dlouhá');
rejects(fn() => Catalog::normalize([], []), 'Neúplný');
check(str_contains(XmlRpcPolicy::advice(['Jetpack'], 'unused', true), 'Nedoporučujeme'), 'Known dependency must win.');
check(str_contains(XmlRpcPolicy::advice([], 'used', true), 'Nedoporučujeme'), 'External dependency must win.');
check(str_contains(XmlRpcPolicy::advice([], 'unknown', true), 'není ověřené'), 'No false assurance.');
check(str_contains(XmlRpcPolicy::advice([], 'unused', false), 'zbývající'), 'False filter is not full XML-RPC disable.');
check(str_contains(XmlRpcPolicy::advice([], 'unused', 'yes'), 'jednoznačný'), 'Invalid filter return remains unknown.');
check(str_contains(XmlRpcPolicy::advice([], 'unused', true), 'Lze zvážit'), 'Conditional recommendation.');
$store = new Store();
$record = $store->load(); $record['items'] = $done;
$store->save($record, ''); $first = $store->load();
check($first['revision'] !== '', 'Persisted revision.');
rejects(fn() => $store->save($record, ''), 'mezitím');
$store->save($first, $first['revision']); $second = $store->load();
check($first['revision'] !== $second['revision'], 'Revision advances.');
$GLOBALS['wpdb']->race = true;
rejects(fn() => $store->save($second, $second['revision']), 'souběžná');
$GLOBALS['wpdb']->race = false;
$record = $store->load(); $GLOBALS['wpdb']->fail = true;
rejects(fn() => $store->save($record, $record['revision']), 'chyba databáze');
$GLOBALS['wpdb']->fail = false;
$GLOBALS['options'][Store::OPTION] = ['schema' => 9]; rejects(fn() => $store->load(), 'nepodporovaný');
$GLOBALS['options'][Store::OPTION] = $second;
$_SERVER['REQUEST_METHOD'] = 'POST';
$GLOBALS['authorized'] = false; rejects(fn() => Access::post('test'), '403');
$GLOBALS['authorized'] = true; $_SERVER['REQUEST_METHOD'] = 'GET'; rejects(fn() => Access::post('test'), '405');
$_SERVER['REQUEST_METHOD'] = 'POST'; $GLOBALS['nonce_ok'] = false; rejects(fn() => Access::post('test'), 'Bad nonce');
$GLOBALS['nonce_ok'] = true; Access::post('test');
foreach ([new \JT\WPCare\Modules\Checklist\Module($store), new \JT\WPCare\Modules\Security\Module($store), new \JT\WPCare\Modules\Reports\Module($store)] as $module) { $module->register(); }
foreach (['jt_care_save' => 'save', 'jt_care_audit' => 'run', 'jt_care_report' => 'export'] as $action => $method) {
    $handler = $GLOBALS['hooks']['admin_post_' . $action][0];
    $GLOBALS['authorized'] = false; rejects(fn() => $handler(), '403');
    $GLOBALS['authorized'] = true; $GLOBALS['nonce_ok'] = false; rejects(fn() => $handler(), 'Bad nonce');
    $GLOBALS['nonce_ok'] = true;
}
// Collector uses local API doubles; fixture contains only an empty include target.
$fixture = sys_get_temp_dir() . '/jt-care-fixture-' . bin2hex(random_bytes(4));
mkdir($fixture . '/wp-admin/includes', 0755, true);
file_put_contents($fixture . '/wp-admin/includes/plugin.php', '<?php');
define('ABSPATH', $fixture . '/');
try {
    $GLOBALS['plugins'] = ['jetpack/jetpack.php' => ['Name' => 'Jetpack', 'Version' => '1'], 'inactive/x.php' => ['Name' => '<b>Other</b>', 'Version' => '2']];
    $GLOBALS['network'] = ['jetpack/jetpack.php'];
    $audit = (new Collector())->collect('unused');
    check(str_contains($audit['facts']['XML-RPC známé návaznosti'], 'Jetpack'), 'Network dependency detection.');
    check(str_contains($audit['facts']['XML-RPC doporučení'], 'Nedoporučujeme'), 'Audit preserves dependency.');
    check(str_contains($audit['facts']['XML-RPC z internetu'], 'Neověřeno'), 'No false reachability claim.');
    check($audit['plugins'][1]['active'] === false, 'Inactive plugin classified.');
    check($audit['facts']['WooCommerce'] === 'Nenačteno', 'WooCommerce absence is safe.');
    $GLOBALS['network'] = []; $GLOBALS['xmlrpc_auth'] = false;
    $audit = (new Collector())->collect('unused');
    check(str_contains($audit['facts']['XML-RPC doporučení'], 'zbývající'), 'Filter false is scoped.');
    $GLOBALS['options'][Store::OPTION]['audit'] = $audit;
} finally {
    unlink($fixture . '/wp-admin/includes/plugin.php');
    rmdir($fixture . '/wp-admin/includes'); rmdir($fixture . '/wp-admin'); rmdir($fixture);
}
$GLOBALS['options'][Store::OPTION]['items']['backup']['note'] = '<script>alert(1)</script>';
$_GET['jt_tab'] = 'checklist';
ob_start(); (new \JT\WPCare\Modules\Checklist\Module($store))->render(); $html = ob_get_clean();
check(!str_contains($html, '<script>'), 'Stored notes must be escaped.');
check(str_contains($html, '&lt;script&gt;'), 'Escaped note is visible.');
$record = $store->load(); $text = Formatter::text($record, 'https://example.test/');
check(str_contains($text, 'Není potvrzením dokončeného servisu'), 'Report is not false completion.');
check(str_contains($text, 'XML-RPC z internetu: Neověřeno'), 'Report retains uncertainty.');
check(str_contains($text, $record['revision']), 'Report identifies revision.');
// Export view and TXT both consume this formatter; print renderer escapes its output.
check(!str_contains($text, '\\nDIAGNOSTIKA'), 'Real newline in report.');
$record['repair_history'] = [['at' => '2026-10-14T20:00:00Z', 'by' => 7, 'state' => 'changed', 'summary' => '4 ověřeno, 0 neověřeno, 0 problém', 'backup' => 'wp-config-test.php']];
$text = Formatter::text($record, 'https://example.test/');
check(str_contains($text, 'OPRAVA WP-CONFIG'), 'Config repair must be included in report.');
check(str_contains($text, 'wp-config-test.php'), 'Report must identify the protected backup.');
echo "Behavior tests passed: {$count}\n";
