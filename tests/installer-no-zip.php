<?php
declare(strict_types=1);
require dirname(__DIR__) . '/installer/Package.php';
require dirname(__DIR__) . '/installer/Installer.php';
require dirname(__DIR__) . '/autoload.php';
use JT\WPCareInstaller\Package;
use JT\WPCareInstaller\Installer;
use JT\WPCare\Core\Release;
if (class_exists(ZipArchive::class)) { throw new RuntimeException('Run this test with php -n (without zip).'); }
$root = dirname(__DIR__);
$payload = $root . '/dist/jt-wp-care-' . Release::VERSION . '-payload.zip';
$digest = hash_file('sha256', $payload);
$files = Package::read($payload, $digest, Release::VERSION, '6.0');
foreach ($files as $name => $bytes) {
    if ($bytes !== file_get_contents($root . '/dist/mu-plugins/jt-wp-care/releases/' . Release::VERSION . '/' . $name)) { throw new RuntimeException('Different extracted bytes'); }
}
$temp = sys_get_temp_dir() . '/jt-nozip-' . bin2hex(random_bytes(12));
mkdir($temp . '/jt-wp-care/releases/0.3.0-alpha', 0755, true);
$base = $temp . '/jt-wp-care';
try {
    $data = file_get_contents($payload);
    $end = strlen($data) - 22;
    $central = unpack('V', substr($data, $end + 16, 4))[1];
    $mutations = [substr($data, 0, -1), $data . 'extra'];
    foreach ([[$central + 38, pack('V', 0120777 << 16)], [$central + 10, pack('v', 8)], [8, pack('v', 8)], [$central + 16, pack('V', 123)], [$central + 42, pack('V', 1)]] as [$offset, $replacement]) {
        $mutations[] = substr_replace($data, $replacement, $offset, strlen($replacement));
    }
    $mutations[] = str_replace('bootstrap.php', '../escape.php', $data);
    foreach ($mutations as $bytes) {
        $bad = $temp . '/bad.zip'; file_put_contents($bad, $bytes);
        try { Package::read($bad, hash_file('sha256', $bad), Release::VERSION, '6.0'); throw new RuntimeException('Invalid ZIP accepted'); }
        catch (DomainException $expected) {}
    }
    copy($root . '/dist/mu-plugins/jt-wp-care.php', $temp . '/jt-wp-care.php');
    file_put_contents($base . '/current.json', json_encode(['schema' => 1, 'active' => '0.3.0-alpha', 'previous' => null]));
    $old = $base . '/releases/0.3.0-alpha';
    file_put_contents($old . '/bootstrap.php', '<?php'); file_put_contents($old . '/autoload.php', '<?php');
    file_put_contents($old . '/release.json', json_encode(array_replace(Release::metadata(), ['version' => '0.3.0-alpha'])));
    $engine = new Installer($temp, hash_file('sha256', $temp . '/jt-wp-care.php'), '6.0');
    if ($engine->install($payload, $digest, Release::VERSION, '0.3.0-alpha')['active'] !== Release::VERSION) { throw new RuntimeException('Update failed'); }
    if ($engine->rollback(Release::VERSION)['active'] !== '0.3.0-alpha') { throw new RuntimeException('Rollback failed'); }
    echo "Without PHP zip: exact extraction, 8 corrupt/unsafe archives, install and rollback passed.\n";
} finally {
    if (realpath(dirname($temp)) !== realpath(sys_get_temp_dir()) || is_link($temp) || !preg_match('/^jt-nozip-[a-f0-9]{24}$/D', basename($temp))) { throw new RuntimeException('Unsafe cleanup'); }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) { if ($file->isDir() && !$file->isLink()) { rmdir($file->getPathname()); } else { unlink($file->getPathname()); } }
    rmdir($temp);
}
