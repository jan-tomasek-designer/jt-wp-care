<?php
declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';
use JT\WPCare\Core\Release;
$dist = dirname(__DIR__) . '/dist';
$v = Release::VERSION;
$manifest = json_decode(file_get_contents($dist . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
if ($manifest['signed'] !== false || $manifest['version'] !== $v) { throw new RuntimeException('Manifest misrepresents trust/version.'); }
foreach ($manifest['assets'] as $file => $expected) {
    if (hash_file('sha256', $dist . '/' . $file) !== $expected['sha256'] || filesize($dist . '/' . $file) !== $expected['bytes']) { throw new RuntimeException('Asset mismatch.'); }
    $zip = new ZipArchive(); $zip->open($dist . '/' . $file);
    $companion = str_starts_with($file, 'jt-wp-care-installer-');
    if ($companion) {
        foreach (['jt-wp-care-installer.php', 'Package.php', 'Installer.php', 'Screen.php', 'loader.sha256'] as $name) {
            if ($zip->getFromName('jt-wp-care-installer/' . $name) !== file_get_contents($dist . '/companion/jt-wp-care-installer/' . $name)) { throw new RuntimeException('Companion mismatch'); }
        }
        if ($zip->numFiles !== 5) { throw new RuntimeException('Unexpected companion file'); }
        $zip->close(); continue;
    }
    $payload = str_ends_with($file, '-payload.zip');
    if ($payload && ($zip->locateName('jt-wp-care.php') !== false || $zip->locateName('jt-wp-care/current.json') !== false)) { throw new RuntimeException('Payload would replace loader/state.'); }
    if (!$payload && ($zip->locateName('jt-wp-care.php') === false || $zip->locateName('jt-wp-care/current.json') === false)) { throw new RuntimeException('Installer is incomplete.'); }
    for ($i = 0; $i < $zip->numFiles; ++$i) {
        $name = $zip->getNameIndex($i);
        if ($payload && !str_starts_with($name, 'jt-wp-care/releases/' . $v . '/')) { throw new RuntimeException('Payload escaped release prefix.'); }
        if (str_contains($name, '..') || preg_match('~(^|/)(tests|docs|work|vendor|\.git)(/|$)~', $name)) { throw new RuntimeException('Unexpected package file.'); }
        if (file_get_contents($dist . '/mu-plugins/' . $name) !== $zip->getFromIndex($i)) { throw new RuntimeException('Packaged bytes differ.'); }
    }
    if ($zip->locateName('jt-wp-care/releases/' . $v . '/bootstrap.php') === false) { throw new RuntimeException('Missing application entry.'); }
    $zip->close();
}
echo "Package contract passed.\n";
