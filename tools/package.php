<?php
declare(strict_types=1);
date_default_timezone_set('UTC');
require dirname(__DIR__) . '/autoload.php';
use JT\WPCare\Core\Release;

if (!class_exists(ZipArchive::class)) { throw new RuntimeException('Local packaging requires PHP zip extension. Hosting does not.'); }
$dist = dirname(__DIR__) . '/dist';
$install = $dist . '/mu-plugins';
$version = Release::VERSION;
$directory = 'jt-wp-care/releases/' . $version;
$release = $install . '/' . $directory;
$meta = json_decode((string) file_get_contents($release . '/release.json'), true, 512, JSON_THROW_ON_ERROR);
if ($meta !== Release::metadata()) { throw new RuntimeException('Build metadata mismatch.'); }
/** Deterministic ordering, timestamps and permissions for byte-identical rebuilds. */
function archiveFiles(string $base, array $paths, string $output): void
{
    $files = [];
    foreach ($paths as $path) {
        if (is_file($base . '/' . $path)) { $files[] = $path; continue; }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base . '/' . $path, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isLink()) { throw new RuntimeException('Symlink in package.'); }
            if ($file->isFile()) { $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1)); }
        }
    }
    sort($files, SORT_STRING);
    $zip = new ZipArchive();
    if ($zip->open($output, ZipArchive::CREATE | ZipArchive::EXCL) !== true) { throw new RuntimeException('Refusing to overwrite archive.'); }
    foreach ($files as $file) {
        if (!$zip->addFile($base . '/' . $file, $file) || !$zip->setMtimeName($file, 946684800)
            || !$zip->setCompressionName($file, ZipArchive::CM_STORE)
            || !$zip->setExternalAttributesName($file, ZipArchive::OPSYS_UNIX, 0100644 << 16)) { throw new RuntimeException('Cannot create archive entry.'); }
    }
    if (!$zip->close()) { throw new RuntimeException('Cannot close archive.'); }
}
$installer = 'jt-wp-care-' . $version . '-install.zip';
$payload = 'jt-wp-care-' . $version . '-payload.zip';
archiveFiles($install, ['jt-wp-care.php', 'jt-wp-care/current.json', $directory], $dist . '/' . $installer);
// No pointer or loader in the updater payload: those belong to the local operator/updater.
archiveFiles($install, [$directory], $dist . '/' . $payload);
$assets = [];
foreach ([$installer, $payload] as $file) { $assets[$file] = ['sha256' => hash_file('sha256', $dist . '/' . $file), 'bytes' => filesize($dist . '/' . $file)]; }
$manifest = Release::metadata() + ['signed' => false, 'assets' => $assets];
file_put_contents($dist . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
$checksums = '';
foreach ($assets as $file => $data) { $checksums .= $data['sha256'] . '  ' . $file . "\n"; }
$checksums .= hash_file('sha256', $dist . '/manifest.json') . "  manifest.json\n";
file_put_contents($dist . '/SHA256SUMS.txt', $checksums);
echo "Install and payload archives prepared; manifest is UNSIGNED (no unattended installation).\n";
