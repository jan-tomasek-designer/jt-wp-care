<?php
declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';

use JT\WPCare\Modules\Repairs\ConfigEditor;

function repairCheck(bool $condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } }
function repairRejects(callable $callback, string $fragment): void
{
    try { $callback(); } catch (DomainException $error) { repairCheck(str_contains($error->getMessage(), $fragment), 'Unexpected rejection: ' . $error->getMessage()); return; }
    throw new RuntimeException('Expected rejection: ' . $fragment);
}

$directory = sys_get_temp_dir() . '/jt-care-repair-' . bin2hex(random_bytes(5));
mkdir($directory, 0700, true);
$path = $directory . '/wp-config.php';
$backup = $directory . '/private';
$source = <<<'PHP'
<?php
define('DB_PASSWORD', 'not-written-in-plain-backup');
define( 'WP_DEBUG', true );
define("WP_DEBUG_LOG", TRUE);
require_once ABSPATH . 'wp-settings.php';
PHP;
file_put_contents($path, $source);
try {
    $editor = new ConfigEditor($path, $backup, ['WP_DEBUG' => true, 'WP_DEBUG_DISPLAY' => false, 'WP_DEBUG_LOG' => true]);
    $inspection = $editor->inspect();
    repairCheck($inspection['ready'] && $inspection['needed'], 'Conventional config should be repairable.');
    $operation = $editor->disableDebug();
    $changed = (string) file_get_contents($path);
    foreach (['WP_DEBUG', 'WP_DEBUG_DISPLAY', 'WP_DEBUG_LOG'] as $name) {
        repairCheck(str_contains($changed, "define('{$name}', false);"), $name . ' was not disabled.');
    }
    $stored = (string) file_get_contents($operation['backup']);
    repairCheck(!str_contains($stored, 'not-written-in-plain-backup'), 'Backup must not expose config as plain response content.');
    $editor->restore($operation);
    repairCheck(file_get_contents($path) === $source, 'Rollback must restore byte-identical config.');

    file_put_contents($path, "<?php\ndefine('WP_DEBUG', getenv('WP_DEBUG'));\nrequire_once ABSPATH . 'wp-settings.php';\n");
    $dynamic = new ConfigEditor($path, $backup, ['WP_DEBUG' => true, 'WP_DEBUG_DISPLAY' => false, 'WP_DEBUG_LOG' => false]);
    repairRejects(fn() => $dynamic->plan((string) file_get_contents($path)), 'nejednoznačně');

    file_put_contents($path, "<?php\nrequire_once ABSPATH . 'wp-settings.php';\n");
    $external = new ConfigEditor($path, $backup, ['WP_DEBUG' => true, 'WP_DEBUG_DISPLAY' => false, 'WP_DEBUG_LOG' => false]);
    repairRejects(fn() => $external->plan((string) file_get_contents($path)), 'není ve wp-config.php');

    $commented = "<?php\n// define('WP_DEBUG', true);\nrequire_once ABSPATH . 'wp-settings.php';\n";
    file_put_contents($path, $commented);
    $commentsAreNotCode = new ConfigEditor($path, $backup, ['WP_DEBUG' => false, 'WP_DEBUG_DISPLAY' => false, 'WP_DEBUG_LOG' => false]);
    $commentPlan = $commentsAreNotCode->plan($commented);
    repairCheck(str_contains($commentPlan, "// define('WP_DEBUG', true);"), 'Commented examples must never be rewritten as executable config.');
    repairCheck(substr_count($commentPlan, "define('WP_DEBUG', false);") === 1, 'Missing executable definition should be inserted once.');

    $mismatch = "<?php\ndefine('WP_DEBUG', false);\nrequire_once ABSPATH . 'wp-settings.php';\n";
    $otherSource = new ConfigEditor($path, $backup, ['WP_DEBUG' => true, 'WP_DEBUG_DISPLAY' => false, 'WP_DEBUG_LOG' => false]);
    repairRejects(fn() => $otherSource->plan($mismatch), 'jiný zdroj');

    file_put_contents($path, $source);
    $blocked = $directory . '/not-a-directory'; file_put_contents($blocked, 'x');
    $fallback = $directory . '/managed/config-backups';
    $fallbackEditor = new ConfigEditor($path, [$blocked . '/backup', $fallback], ['WP_DEBUG' => true, 'WP_DEBUG_DISPLAY' => false, 'WP_DEBUG_LOG' => true]);
    repairCheck($fallbackEditor->inspect()['ready'], 'Writable managed fallback should make repair available.');
    $fallbackOperation = $fallbackEditor->disableDebug();
    repairCheck(dirname($fallbackOperation['backup']) === $fallback, 'Backup should fall back to managed Care directory.');
    $fallbackEditor->restore($fallbackOperation);
    foreach (array_diff(scandir($fallback), ['.', '..']) as $file) { unlink($fallback . '/' . $file); }
    rmdir($fallback); rmdir(dirname($fallback)); unlink($blocked);
} finally {
    if (is_dir($backup)) { foreach (array_diff(scandir($backup), ['.', '..']) as $file) { unlink($backup . '/' . $file); } rmdir($backup); }
    if (is_file($path)) { unlink($path); }
    rmdir($directory);
}
echo "Safe wp-config debug repair and rollback passed.\n";
