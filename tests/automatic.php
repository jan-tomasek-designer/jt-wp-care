<?php
declare(strict_types=1);
require __DIR__ . '/wp-doubles.php';
require dirname(__DIR__) . '/autoload.php';
use JT\WPCare\Modules\Automatic\Evaluation;
use JT\WPCare\Modules\Automatic\Module;
use JT\WPCare\Modules\Checklist\Catalog;
use JT\WPCare\Core\Store;
function verify(bool $ok, string $reason): void { if (!$ok) { throw new RuntimeException($reason); } }
$now = strtotime('2026-10-12 12:00 UTC');
verify(Evaluation::php('8.3.30', $now)['state'] === 'done', 'Security-only branch remains supported');
verify(str_contains(Evaluation::php('8.3.30', $now)['text'], 'bezpečnostní opravy'), 'Support kind');
verify(Evaluation::php('8.6.0RC1', $now)['state'] === 'unknown', 'Unknown/pre-release PHP');
verify(Evaluation::php('8.3.30', strtotime('2027-04-12 UTC'))['state'] === 'unknown', 'Policy expires');
verify(Evaluation::php('8.2.30', strtotime('2027-01-01 UTC'))['state'] === 'problem', 'Security EOL boundary');
$checks = ['php_version' => '8.3.30', 'file_edit_disabled' => true, 'debug' => true, 'offers' => [
    'plugins' => ['count' => 3, 'at' => $now], 'wordpress' => ['count' => 0, 'at' => $now], 'theme' => ['count' => 4, 'at' => $now - 90000]]];
$results = Evaluation::collect($checks, $now);
verify($results['file_edit']['state'] === 'done' && $results['debug']['state'] === 'problem', 'Typed checks');
verify($results['plugins']['state'] === 'problem', 'Known offer needs attention');
verify($results['wordpress']['state'] === 'unknown', 'No offer is not proof of current version');
verify($results['theme']['state'] === 'unknown', 'Stale cache is not current');
verify(!isset($results['backup'], $results['smtp'], $results['order']), 'No invented human tests');
$audit = ['at' => gmdate('c', $now), 'automatic' => $results];
verify(Evaluation::results($audit, $now + 86401)['file_edit']['state'] === 'unknown', 'Snapshot expires');
verify(Module::due(['audit' => $audit], $now + 3600), 'Hourly refresh');
verify(!Module::due(['audit' => $audit], $now + 3599), 'No repeated refresh');
verify(Module::due(['audit' => ['at' => gmdate('c', $now)]], $now), 'Old format refreshed immediately');
$GLOBALS['options'][Module::FORCE_REFRESH] = '2026-10-12T12:00:00Z';
verify(Module::due(['audit' => $audit], $now + 1), 'Config repair forces a new-process diagnostic refresh');
unset($GLOBALS['options'][Module::FORCE_REFRESH]);
$GLOBALS['options']['jt_wp_care_update_plan'] = ['state' => 'verifying'];
verify(!Module::due(['audit' => $audit], $now + 3600), 'Post-update gate owns diagnostics while pending');
unset($GLOBALS['options']['jt_wp_care_update_plan']);
$fixture = sys_get_temp_dir() . '/jt-auto-' . bin2hex(random_bytes(8));
mkdir($fixture . '/wp-admin/includes', 0755, true); file_put_contents($fixture . '/wp-admin/includes/plugin.php', '<?php');
define('ABSPATH', $fixture . '/');
try {
    $store = new Store(); $record = $store->load();
    $record['items'] = ['backup' => ['state' => 'done', 'note' => 'Manual evidence', 'at' => '2026-10-01', 'by' => 7],
        'php' => ['state' => 'done', 'note' => 'Original PHP note', 'at' => '2026-10-01', 'by' => 7]];
    $record['external_use'] = 'unused'; $record['external_note'] = 'Human attestation'; $record['external_at'] = '2026-10-01';
    $store->save($record, ''); $before = $store->load();
    $GLOBALS['options'][Module::FORCE_REFRESH] = '2026-10-12T12:00:00Z';
    $module = new Module($store);
    verify($module->refresh($before['revision']), 'First automatic snapshot saved');
    verify(!isset($GLOBALS['options'][Module::FORCE_REFRESH]), 'Successful refresh clears forced diagnostic flag');
    $after = $store->load();
    foreach (['items', 'external_use', 'external_note', 'external_at'] as $key) { verify($after[$key] === $before[$key], 'Preserves human evidence ' . $key); }
    verify(!$module->refresh($after['revision']), 'Fresh snapshot not written twice');
    $input = []; foreach (Catalog::items() as $id => $definition) { $input[$id] = ['state' => 'unknown', 'note' => '']; }
    unset($input['php'], $input['file_edit']);
    $rows = Catalog::normalize($input, $after['items'], $after['audit']);
    verify($rows['php'] === $before['items']['php'], 'Automatic fields preserve old human record');
    $module->register(); $handler = $GLOBALS['hooks']['admin_post_jt_care_auto_audit'][0];
    $GLOBALS['authorized'] = false;
    try { $handler(); throw new LogicException('Unauthorized request accepted'); } catch (RuntimeException $e) { verify(str_contains($e->getMessage(), '403'), 'Capability guard'); }
    $GLOBALS['authorized'] = true; $_SERVER['REQUEST_METHOD'] = 'POST'; $GLOBALS['nonce_ok'] = false;
    try { $handler(); throw new LogicException('Bad nonce accepted'); } catch (RuntimeException $e) { verify($e->getMessage() === 'Bad nonce', 'Nonce guard'); }
} finally {
    unlink($fixture . '/wp-admin/includes/plugin.php'); rmdir($fixture . '/wp-admin/includes'); rmdir($fixture . '/wp-admin'); rmdir($fixture);
}
echo "Automatic checks, expiry, preservation and access tests passed.\n";
