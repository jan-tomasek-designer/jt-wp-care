<?php
declare(strict_types=1);
// Parent test creates isolated fixtures; each loader executes in a separate process.
$root = dirname(__DIR__);
if (isset($argv[1])) {
    define('ABSPATH', __DIR__ . '/');
    $GLOBALS['wp_version'] = '6.0';
    $GLOBALS['notices'] = [];
    function add_action($hook, $callback) { $GLOBALS['notices'][] = $callback; }
    if (($argv[2] ?? '') !== '') { define('JT_WP_CARE_PINNED_RELEASE', $argv[2]); }
    require $argv[1] . '/jt-wp-care.php';
    echo json_encode(['loaded' => $GLOBALS['fixture_loaded'] ?? null, 'notices' => count($GLOBALS['notices'])]);
    exit;
}
$temp = sys_get_temp_dir() . '/jt-loader-' . bin2hex(random_bytes(8));
mkdir($temp . '/jt-wp-care/releases', 0755, true);
copy($root . '/mu-plugin/jt-wp-care.php', $temp . '/jt-wp-care.php');
function releaseFixture(string $dir, string $version, array $changes = []): void
{
    mkdir($dir . '/jt-wp-care/releases/' . $version, 0755, true);
    $path = $dir . '/jt-wp-care/releases/' . $version;
    file_put_contents($path . '/release.json', json_encode(array_replace(['schema' => 1, 'version' => $version, 'loader_protocol' => 1, 'data_schema' => 1, 'php_min' => '8.0', 'wp_min' => '6.0'], $changes)));
    file_put_contents($path . '/bootstrap.php', '<?php $GLOBALS["fixture_loaded"] = ' . var_export($version, true) . ';');
    file_put_contents($path . '/autoload.php', '<?php');
}
function launch(string $temp, ?array $state, ?string $pin = null): array
{
    file_put_contents($temp . '/jt-wp-care/current.json', $state === null ? '{broken' : json_encode($state));
    $pipes = [];
    $proc = proc_open([PHP_BINARY, __FILE__, $temp, $pin ?? ''], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($proc) !== 0 || $err !== '') { throw new RuntimeException('Loader process failed: ' . $err); }
    return json_decode($out, true, 512, JSON_THROW_ON_ERROR);
}
$count = 0;
function expectLoad(string $temp, ?array $state, ?string $expected, ?string $pin = null): void
{
    global $count;
    $result = launch($temp, $state, $pin);
    if ($result['loaded'] !== $expected) { throw new RuntimeException('Unexpected loader selection: ' . json_encode($result)); }
    ++$count;
}
try {
    releaseFixture($temp, '0.3.0-alpha'); releaseFixture($temp, '0.2.1');
    releaseFixture($temp, '9.0.0', ['php_min' => '99.0']);
    releaseFixture($temp, '9.1.0', ['wp_min' => '99.0']);
    releaseFixture($temp, '9.2.0', ['loader_protocol' => 2]);
    releaseFixture($temp, '9.3.0', ['data_schema' => 2]);
    releaseFixture($temp, '9.4.0', ['version' => '0.2.1']);
    $state = ['schema' => 1, 'active' => '0.3.0-alpha', 'previous' => '0.2.1'];
    expectLoad($temp, $state, '0.3.0-alpha');
    foreach (['8.0.0', '9.0.0', '9.1.0', '9.2.0', '9.3.0', '9.4.0'] as $bad) {
        $s = array_replace($state, ['active' => $bad]);
        expectLoad($temp, $s, '0.2.1');
        if (launch($temp, $s)['notices'] !== 1) { throw new RuntimeException('Fallback must be visible.'); }
    }
    expectLoad($temp, array_replace($state, ['active' => '../../outside']), null);
    expectLoad($temp, array_replace($state, ['schema' => 2]), null);
    expectLoad($temp, null, null);
    expectLoad($temp, null, '0.2.1', '0.2.1');
    expectLoad($temp, $state, null, '../../outside');
    expectLoad($temp, $state, null, '8.0.0');
    // Removing bootstrap causes preflight fallback without executing the invalid release.
    unlink($temp . '/jt-wp-care/releases/0.3.0-alpha/bootstrap.php');
    expectLoad($temp, $state, '0.2.1');
    unlink($temp . '/jt-wp-care/releases/0.2.1/autoload.php');
    expectLoad($temp, $state, null);
    // Restore a loadable release so the symlink rejection cannot pass for another reason.
    file_put_contents($temp . '/jt-wp-care/releases/0.2.1/autoload.php', '<?php');
    // On hosts that permit symlinks, reject linked state.
    if (DIRECTORY_SEPARATOR === '/') {
        unlink($temp . '/jt-wp-care/current.json');
        file_put_contents($temp . '/linked.json', json_encode($state));
        symlink($temp . '/linked.json', $temp . '/jt-wp-care/current.json');
        $pipes = []; $p = proc_open([PHP_BINARY, __FILE__, $temp, ''], [1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
        $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($p) !== 0 || $err !== '' || json_decode($out, true)['loaded'] !== null) { throw new RuntimeException('Linked state accepted.'); }
        ++$count;
    }
    echo "Versioned loader passed: {$count} scenarios\n";
} finally {
    // Only our randomly-created fixture tree; never follow links during cleanup.
    if (realpath(dirname($temp)) !== realpath(sys_get_temp_dir()) || !preg_match('/^jt-loader-[a-f0-9]{16}$/', basename($temp)) || is_link($temp)) { throw new RuntimeException('Unsafe fixture cleanup path.'); }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) { if ($file->isDir() && !$file->isLink()) { rmdir($file->getPathname()); } else { unlink($file->getPathname()); } }
    rmdir($temp);
}
