<?php
declare(strict_types=1);
require dirname(__DIR__) . '/installer/Package.php';
require dirname(__DIR__) . '/installer/Installer.php';
use JT\WPCareInstaller\Package;
use JT\WPCareInstaller\Installer;
require dirname(__DIR__) . '/autoload.php';
use JT\WPCare\Core\Release;
$root = dirname(__DIR__);
$version = Release::VERSION;
$payload = $root . '/dist/jt-wp-care-' . $version . '-payload.zip';
$hash = hash_file('sha256', $payload);
$temp = sys_get_temp_dir() . '/jt-updater-' . bin2hex(random_bytes(12));
mkdir($temp . '/jt-wp-care/releases/0.3.0-alpha', 0755, true);
$base = $temp . '/jt-wp-care';
copy($root . '/dist/mu-plugins/jt-wp-care.php', $temp . '/jt-wp-care.php');
$loaderHash = hash_file('sha256', $temp . '/jt-wp-care.php');
$count = 0;
function ok(bool $value, string $message): void { global $count; ++$count; if (!$value) { throw new RuntimeException($message); } }
function rejected(callable $test): void { try { $test(); } catch (DomainException $e) { ok(true, 'Rejected'); return; } throw new RuntimeException('Unsafe input accepted'); }
function badZip(string $temp, array $files, string $version): void {
    $path = $temp . '/bad-' . bin2hex(random_bytes(6)) . '.zip';
    $zip = new ZipArchive(); $zip->open($path, ZipArchive::CREATE);
    foreach ($files as $name => $bytes) { $zip->addFromString($name, $bytes); $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0100644 << 16); }
    $zip->close();
    rejected(fn() => Package::read($path, hash_file('sha256', $path), $version, '6.0'));
}
try {
    $old = $base . '/releases/0.3.0-alpha';
    file_put_contents($old . '/bootstrap.php', '<?php'); file_put_contents($old . '/autoload.php', '<?php');
    file_put_contents($old . '/release.json', json_encode(array_replace(Release::metadata(), ['version' => '0.3.0-alpha'])));
    $state = ['schema' => 1, 'active' => '0.3.0-alpha', 'previous' => null];
    file_put_contents($base . '/current.json', json_encode($state));
    file_put_contents($base . '/service-data-sentinel.txt', 'manual notes remain');
    $engine = new Installer($temp, $loaderHash, '6.0');
    $files = Package::read($payload, $hash, $version, '6.0');
    ok(isset($files['bootstrap.php']), 'Read trusted payload');
    rejected(fn() => Package::read($payload, str_repeat('0', 64), $version, '6.0'));
    rejected(fn() => Package::read($payload, $hash, '../outside', '6.0'));
    rejected(fn() => Package::read($payload, $hash, $version, '5.9'));
    rejected(fn() => new Installer($temp, str_repeat('0', 64), '6.0'));
    $prefix = 'jt-wp-care/releases/' . $version . '/';
    $zipFiles = []; foreach ($files as $name => $bytes) { $zipFiles[$prefix . $name] = $bytes; }
    foreach (['../outside.php', 'src/../../outside.php', '/absolute.php', 'src\\evil.php', 'src/Evil.PHP', 'assets/shell.php', 'jt-wp-care.php'] as $name) {
        badZip($temp, $zipFiles + [$prefix . $name => '<?php'], $version);
    }
    badZip($temp, $zipFiles + [$prefix . 'src/core/Release.php' => '<?php'], $version);
    $bad = $zipFiles; unset($bad[$prefix . 'bootstrap.php']); badZip($temp, $bad, $version);
    foreach ([['data_schema' => 2], ['loader_protocol' => 2], ['php_min' => '99.0'], ['wp_min' => '99.0'], ['version' => '0.0.0']] as $change) {
        $bad = $zipFiles; $bad[$prefix . 'release.json'] = json_encode(array_replace(Release::metadata(), $change)); badZip($temp, $bad, $version);
    }
    $linked = $temp . '/linked.zip'; copy($payload, $linked);
    $zip = new ZipArchive(); $zip->open($linked); $zip->setExternalAttributesName($prefix . 'bootstrap.php', ZipArchive::OPSYS_UNIX, 0120777 << 16); $zip->close();
    rejected(fn() => Package::read($linked, hash_file('sha256', $linked), $version, '6.0'));
    rejected(fn() => $engine->install($payload, $hash, $version, '0.2.0-alpha'));
    $lock = fopen($base . '/.update.lock', 'c'); flock($lock, LOCK_EX);
    rejected(fn() => $engine->install($payload, $hash, $version, '0.3.0-alpha'));
    flock($lock, LOCK_UN); fclose($lock);
    ok($engine->state() === $state, 'All rejections kept the pointer');
    $result = $engine->install($payload, $hash, $version, '0.3.0-alpha');
    ok($result['active'] === $version && $result['previous'] === '0.3.0-alpha', 'Version switch');
    ok($engine->state() === $result, 'Pointer persisted');
    foreach ($files as $name => $bytes) { ok(file_get_contents($base . '/releases/' . $version . '/' . $name) === $bytes, 'Installed bytes'); }
    ok(file_get_contents($base . '/service-data-sentinel.txt') === 'manual notes remain', 'Data untouched');
    ok(is_file($old . '/bootstrap.php'), 'Previous release preserved');
    rejected(fn() => $engine->install($payload, $hash, $version, $version));
    rejected(fn() => $engine->rollback('0.0.0'));
    $back = $engine->rollback($version);
    ok($back['active'] === '0.3.0-alpha', 'Rollback');
    rejected(fn() => $engine->install($payload, $hash, $version, '0.3.0-alpha'));
    ok($engine->rollback('0.3.0-alpha')['active'] === $version, 'Return to retained release');
    if (DIRECTORY_SEPARATOR === '/') {
        rename($base . '/current.json', $base . '/real.json'); symlink($base . '/real.json', $base . '/current.json');
        rejected(fn() => $engine->state()); unlink($base . '/current.json'); rename($base . '/real.json', $base . '/current.json');
    }
    echo "Installer tests passed: {$count}\n";
} finally {
    if (realpath(dirname($temp)) !== realpath(sys_get_temp_dir()) || is_link($temp) || !preg_match('/^jt-updater-[a-f0-9]{24}$/D', basename($temp))) { throw new RuntimeException('Unsafe cleanup'); }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) { if ($file->isDir() && !$file->isLink()) { rmdir($file->getPathname()); } else { unlink($file->getPathname()); } }
    rmdir($temp);
}
