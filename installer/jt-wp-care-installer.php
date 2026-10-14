<?php
/**
 * Plugin Name: JT WP Care – instalátor aktualizací
 * Description: Ručně potvrzená aktualizace existujícího JT WP Care z ověřeného payload ZIPu.
 * Version: 0.4.0-alpha
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */
if (!defined('ABSPATH')) { return; }
require_once __DIR__ . '/Package.php';
require_once __DIR__ . '/Installer.php';
require_once __DIR__ . '/Repository.php';
require_once __DIR__ . '/Screen.php';
(new \JT\WPCareInstaller\Screen())->register();
