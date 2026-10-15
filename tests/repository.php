<?php
declare(strict_types=1);
require dirname(__DIR__) . '/installer/Package.php';
require dirname(__DIR__) . '/installer/Repository.php';
use JT\WPCareInstaller\Repository;
function wp_strip_all_tags($s) { return strip_tags($s); }
function wp_http_validate_url($s) { return $s; }
function is_wp_error($r) { return false; }
function wp_remote_retrieve_response_code($r) { return $r['code']; }
function wp_remote_retrieve_header($r, $h) { return $r[$h] ?? ''; }
function wp_remote_retrieve_body($r) { return $r['body'] ?? ''; }
function wp_safe_remote_get($url, $args) {
    if ($args['redirection'] !== 0 || $args['limit_response_size'] < 1) { throw new RuntimeException('Unbounded request'); }
    return array_shift($GLOBALS['responses']);
}
function verify($ok, $why) { if (!$ok) { throw new RuntimeException($why); } }
function reject(callable $call) { try { $call(); } catch (DomainException $e) { return; } throw new RuntimeException('Expected rejection'); }
$url = 'https://github.com/' . Repository::REPO . '/releases/download/v0.7.0-alpha/';
$release = ['draft' => false, 'published_at' => '2026-10-12', 'prerelease' => true, 'tag_name' => 'v0.7.0-alpha', 'body' => '<b>Changes</b>', 'assets' => []];
foreach (['manifest.json', 'jt-wp-care-0.7.0-alpha-payload.zip'] as $file) { $release['assets'][] = ['name' => $file, 'state' => 'uploaded', 'browser_download_url' => $url . $file]; }
verify(Repository::choose([$release], '0.6.0-alpha', false) === null, 'Alpha requires explicit opt-in');
verify(Repository::choose([$release], '0.6.0-alpha', true)['version'] === '0.7.0-alpha', 'Published release');
verify(Repository::choose([$release], '0.7.0-alpha', true) === null, 'No downgrade/reinstall');
$bad = $release; $bad['draft'] = true;
verify(Repository::choose([$bad], '0.6.0-alpha', true) === null, 'Draft rejected');
$bad = $release; $bad['assets'][0]['browser_download_url'] = 'https://evil.test/manifest.json';
verify(Repository::choose([$bad], '0.6.0-alpha', true) === null, 'Foreign asset rejected');
foreach (['http://github.com/x', 'https://github.com.evil.test/x', 'https://user@github.com/x', 'https://127.0.0.1/x', 'https://github.com/other/repo/releases/download/x', 'https://api.github.com/user', $url . 'manifest.json#x'] as $bad) { verify(!Repository::allowed($bad), 'URL rejected'); }
$repo = new Repository();
$manifest = ['schema' => 1, 'version' => '0.7.0-alpha', 'loader_protocol' => 1, 'data_schema' => 1, 'php_min' => '8.0', 'wp_min' => '6.0',
    'assets' => ['jt-wp-care-0.7.0-alpha-payload.zip' => ['bytes' => 1234, 'sha256' => str_repeat('a', 64)]]];
$GLOBALS['responses'] = [['code' => 200, 'body' => json_encode([$release])], ['code' => 200, 'body' => json_encode($manifest)]];
$offer = $repo->check('0.6.0-alpha', true, '6.0');
verify($offer['bytes'] === 1234 && $offer['sha256'] === str_repeat('a', 64) && $offer['expected'] === '0.6.0-alpha', 'Pinned manifest and source version');
$manifest['assets']['jt-wp-care-0.7.0-alpha-payload.zip']['bytes'] = 9000000;
$GLOBALS['responses'] = [['code' => 200, 'body' => json_encode([$release])], ['code' => 200, 'body' => json_encode($manifest)]];
reject(fn() => $repo->check('0.6.0-alpha', true, '6.0'));
$GLOBALS['responses'] = [['code' => 302, 'location' => 'https://evil.test/x']]; reject(fn() => $repo->get($url . 'manifest.json', 100));
$GLOBALS['responses'] = [['code' => 403]]; reject(fn() => $repo->get(Repository::API, 100));
$GLOBALS['responses'] = [['code' => 200, 'body' => str_repeat('x', 101)]]; reject(fn() => $repo->get(Repository::API, 100));
$GLOBALS['responses'] = [['code' => 200, 'body' => 'corrupt']];
// Invalid hash must fail before even creating a temporary file.
define('ABSPATH', dirname(__DIR__) . '/tests/repository-fixture/');
$dir = ABSPATH . 'wp-admin/includes'; mkdir($dir, 0755, true); file_put_contents($dir . '/file.php', '<?php');
try { reject(fn() => $repo->download(['url' => $url . 'payload.zip', 'bytes' => 7, 'sha256' => str_repeat('0', 64)])); }
finally { unlink($dir . '/file.php'); rmdir($dir); rmdir(dirname($dir)); rmdir(dirname(dirname($dir))); }
echo "Repository selection, HTTPS boundaries, limits and integrity tests passed.\n";
