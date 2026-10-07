<?php
declare(strict_types=1);

// Build locally. The host only receives dist/mu-plugins through FTP.
$root = dirname(__DIR__);
$target = $root . '/dist/mu-plugins';
if (file_exists($root . '/dist')) {
    fwrite(STDERR, "dist already exists. Move it aside before building again.\n");
    exit(1);
}
function copyTree(string $source, string $target): void
{
    if (is_dir($source)) {
        if (!mkdir($target, 0755, true) && !is_dir($target)) {
            throw new RuntimeException('Cannot create build directory.');
        }
        foreach (new DirectoryIterator($source) as $item) {
            if (!$item->isDot()) {
                copyTree($item->getPathname(), $target . '/' . $item->getFilename());
            }
        }
        return;
    }
    if (!copy($source, $target)) {
        throw new RuntimeException('Cannot copy build file.');
    }
}
mkdir($target . '/jt-wp-care', 0755, true);
copyTree($root . '/mu-plugin/jt-wp-care.php', $target . '/jt-wp-care.php');
foreach (['bootstrap.php', 'autoload.php', 'src', 'assets'] as $path) {
    copyTree($root . '/' . $path, $target . '/jt-wp-care/' . $path);
}
echo "Build ready: dist/mu-plugins\n";
