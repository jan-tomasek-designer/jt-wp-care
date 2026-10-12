<?php
declare(strict_types=1);
require dirname(__DIR__) . '/installer/Installer.php';
require dirname(__DIR__) . '/installer/Screen.php';
use JT\WPCareInstaller\Installer;
use JT\WPCareInstaller\Screen;
if (isset($argv[1])) {
    $function = $argv[1];
    if (function_exists($function)) { throw new RuntimeException('Test function was not disabled'); }
    try { Installer::environment(); throw new RuntimeException('Disabled function accepted'); }
    catch (DomainException $e) { if (!str_contains($e->getMessage(), $function)) { throw $e; } }
    echo "Disabled host function detected before installation: {$function}\n";
    exit;
}
$text = Screen::diagnostic(new Error('secret-token /private/server/path'), 'kontrola prostředí');
foreach (['secret-token', '/private/server/path', __DIR__] as $secret) { if (str_contains($text, $secret)) { throw new RuntimeException('Runtime details leaked'); } }
foreach (['PHP-Error', 'kontrola prostředí', '0.2.0-alpha', 'složka:', 'nepotvrzuje'] as $expected) { if (!str_contains($text, $expected)) { throw new RuntimeException('Missing diagnostic context'); } }
if (!str_contains(Screen::diagnostic(new DomainException('Ověřený důvod'), 'zápis'), 'Ověřený důvod')) { throw new RuntimeException('Safe domain reason lost'); }
echo "Safe installer diagnostics passed.\n";
