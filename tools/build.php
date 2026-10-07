<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/autoload.php';
use JT\WPCare\Core\Release;

$root = dirname(__DIR__);
$target = $root . '/dist/mu-plugins';
$tag = $argv[1] ?? null;
if ($tag !== null && $tag !== 'v' . Release::VERSION) {
    fwrite(STDERR, "Tag must match application version.\n"); exit(1);
}
if (file_exists($root . '/dist')) {
    fwrite(STDERR, "dist already exists. Move it aside before building again.\n"); exit(1);
}
function copyTree(string $source, string $target): void
{
    if (is_link($source)) { throw new RuntimeException('Build refuses symlinks.'); }
    if (is_dir($source)) {
        if (!mkdir($target, 0755, true) && !is_dir($target)) { throw new RuntimeException('Cannot create directory.'); }
        foreach (new DirectoryIterator($source) as $item) {
            if (!$item->isDot() && $item->getFilename() !== '.gitkeep') { copyTree($item->getPathname(), $target . '/' . $item->getFilename()); }
        }
    } elseif (preg_match('/\.(php|css|js)$/', $source)) {
        if (file_put_contents($target, str_replace("\r\n", "\n", (string) file_get_contents($source))) === false) { throw new RuntimeException('Cannot copy text file.'); }
    } elseif (!copy($source, $target)) { throw new RuntimeException('Cannot copy build file.'); }
}
function writeJson(string $file, array $data): void
{
    if (file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n") === false) { throw new RuntimeException('Cannot write metadata.'); }
}
$release = $target . '/jt-wp-care/releases/' . Release::VERSION;
if (!mkdir($release, 0755, true)) { throw new RuntimeException('Cannot create release.'); }
copyTree($root . '/mu-plugin/jt-wp-care.php', $target . '/jt-wp-care.php');
foreach (['bootstrap.php', 'autoload.php', 'src', 'assets'] as $path) { copyTree($root . '/' . $path, $release . '/' . $path); }
writeJson($release . '/release.json', Release::metadata());
writeJson($target . '/jt-wp-care/current.json', ['schema' => 1, 'active' => Release::VERSION, 'previous' => null]);
copyTree($root . '/installer', $root . '/dist/companion/jt-wp-care-installer');
file_put_contents($root . '/dist/companion/jt-wp-care-installer/loader.sha256', hash_file('sha256', $target . '/jt-wp-care.php') . "\n");

echo "Build ready: dist/mu-plugins, version " . Release::VERSION . "\n";
