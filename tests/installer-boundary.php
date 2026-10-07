<?php
declare(strict_types=1);
require dirname(__DIR__) . '/installer/Screen.php';
function current_user_can($cap) { return $GLOBALS['caps'][$cap] ?? false; }
function wp_die($message, $title = '', $args = []) { throw new RuntimeException((string) ($args['response'] ?? 500)); }
function is_multisite() { return $GLOBALS['multi'] ?? false; }
function wp_is_file_mod_allowed($context) { return $GLOBALS['allowed'] ?? true; }
function check_admin_referer($action) { if (!($GLOBALS['nonce'] ?? false)) { throw new RuntimeException('nonce'); } }
function wp_unslash($value) { return $value; }
function esc_html($value) { return $value; }
$screen = new JT\WPCareInstaller\Screen();
$boundary = new ReflectionMethod($screen, 'boundary');
$boundary->setAccessible(true);
foreach (['install', 'rollback'] as $method) {
    foreach (['no_caps', 'no_update', 'get', 'nonce', 'multisite', 'file_mods', 'unconfirmed'] as $case) {
        $GLOBALS['caps'] = ['manage_options' => true, 'update_plugins' => true];
        $GLOBALS['multi'] = false; $GLOBALS['allowed'] = true; $GLOBALS['nonce'] = true;
        $_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = ['confirmed' => 'yes'];
        switch ($case) {
            case 'no_caps': $GLOBALS['caps'] = []; break;
            case 'no_update': $GLOBALS['caps']['update_plugins'] = false; break;
            case 'get': $_SERVER['REQUEST_METHOD'] = 'GET'; break;
            case 'nonce': $GLOBALS['nonce'] = false; break;
            case 'multisite': $GLOBALS['multi'] = true; break;
            case 'file_mods': $GLOBALS['allowed'] = false; break;
            case 'unconfirmed': $_POST = []; break;
        }
        try { $boundary->invoke($screen, 'jt_care_' . $method); throw new LogicException('Unsafe request accepted'); }
        catch (DomainException $e) { if (!in_array($case, ['multisite', 'file_mods', 'unconfirmed'], true)) { throw $e; } }
        catch (RuntimeException $e) {
            $expected = ['no_caps' => '403', 'no_update' => '403', 'get' => '405', 'nonce' => 'nonce'][$case] ?? '';
            if ($e->getMessage() !== $expected) { throw $e; }
        }
    }
}
echo "Installer request-boundary tests passed: 14\n";
