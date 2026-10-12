<?php
declare(strict_types=1);
namespace JT\WPCare\Modules\Security;

/** Read-only local snapshot. No HTTP requests, email or update refreshes. */
final class Collector
{
    public function collect(string $externalUse): array
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $plugins = get_plugins();
        $updates = get_site_transient('update_plugins');
        $coreUpdates = get_site_transient('update_core');
        $themeUpdates = get_site_transient('update_themes');
        $theme = wp_get_theme();
        $parent = $theme->parent();
        $auth = apply_filters('xmlrpc_enabled', true);
        $known = [];
        $rows = [];
        $smtp = [];
        foreach ($plugins as $file => $data) {
            $active = is_plugin_active($file) || is_plugin_active_for_network($file);
            if ($active && in_array($file, ['wp-mail-smtp/wp_mail_smtp.php', 'fluent-smtp/fluent-smtp.php', 'post-smtp/postman-smtp.php', 'easy-wp-smtp/easy-wp-smtp.php', 'wp-smtp/wp-smtp.php'], true)) { $smtp[] = wp_strip_all_tags($data['Name']); }
            // Exact identifiers; no inference from translated display names.
            if ($active && $file === 'jetpack/jetpack.php') { $known[] = 'Jetpack'; }
            $offer = is_object($updates) && isset($updates->response[$file]) ? $updates->response[$file] : null;
            $latest = is_object($offer) && isset($offer->new_version) ? (string) $offer->new_version : '';
            $rows[] = ['name' => wp_strip_all_tags($data['Name']), 'version' => (string) $data['Version'], 'active' => $active, 'file' => $file, 'update_version' => $latest,
                'update' => $latest !== '' ? 'Nabídka ' . $latest . ' (uložená cache)' : 'V uložené cache není nabídka; aktuálnost neověřena'];
        }
        if (defined('JETPACK__VERSION') && !in_array('Jetpack', $known, true)) { $known[] = 'Jetpack (načtený kód)'; }
        $wc = defined('WC_VERSION') ? (string) WC_VERSION : 'Nenačteno';
        $db = get_option('woocommerce_db_version', 'Nezjištěno');
        $coreOffers = [];
        foreach (is_object($coreUpdates) && is_array($coreUpdates->updates ?? null) ? $coreUpdates->updates : [] as $offer) {
            if (is_object($offer) && ($offer->response ?? '') === 'upgrade' && isset($offer->current)) { $coreOffers[] = (string) $offer->current; }
        }
        $themeOffers = [];
        foreach (is_object($themeUpdates) && is_array($themeUpdates->response ?? null) ? $themeUpdates->response : [] as $slug => $offer) {
            if (is_array($offer) && isset($offer['new_version'])) { $themeOffers[] = $slug . ' → ' . $offer['new_version']; }
        }
        $cacheTime = static fn($cache): string => is_object($cache) && !empty($cache->last_checked) ? gmdate('c', (int) $cache->last_checked) : 'čas nezjištěn';
        $facts = [
            'WordPress' => get_bloginfo('version'),
            'Aktualizace WordPressu' => ($coreOffers ? implode(', ', $coreOffers) : 'V cache není nabídka; aktuálnost neověřena') . ' — cache: ' . $cacheTime($coreUpdates),
            'Aktualizace šablon' => ($themeOffers ? implode('; ', $themeOffers) : 'V cache není nabídka; aktuálnost neověřena') . ' — cache: ' . $cacheTime($themeUpdates),
            'SMTP' => ($smtp ? implode(', ', $smtp) : 'Známý aktivní SMTP plugin nezjištěn; jiná konfigurace není vyloučena') . ' — nastavení a doručení neověřeno',
            'PHP' => PHP_VERSION . ' — podpora větve je vyhodnocena v automatických kontrolách; aktuálnost opravné verze a kompatibilita nejsou potvrzené',
            'Databáze' => (string) $GLOBALS['wpdb']->db_version(),
            'Šablona' => $theme->get('Name') . ' ' . $theme->get('Version'),
            'Parent / child' => $parent ? 'Child theme; parent: ' . $parent->get('Name') . ' ' . $parent->get('Version') : 'Samostatná šablona',
            'WooCommerce' => $wc,
            'WooCommerce DB' => defined('WC_VERSION') ? (string) $db . ' — porovnání verzí samo nepotvrzuje dokončení migrací' : 'Netýká se / WooCommerce nenačteno',
            'Wordfence' => is_plugin_active('wordfence/wordfence.php') ? 'Aktivní plugin; firewall a scan nejsou ověřeny' : 'Aktivní běžný plugin nezjištěn; jiný způsob instalace neověřen',
            '2FA' => 'Pokrytí účtů neověřeno',
            'DISALLOW_FILE_EDIT' => defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT ? 'Zapnuto' : 'Není zapnuto touto konstantou; ostatní omezení neověřena',
            'WP_DEBUG' => defined('WP_DEBUG') && WP_DEBUG ? 'Zapnuto' : 'Vypnuto',
            'WP_DEBUG_DISPLAY' => defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY ? 'Povoleno nastavením WordPressu; skutečné zobrazení zvenčí neověřeno' : 'Vypnuto nastavením WordPressu',
            'PHP display_errors' => in_array(strtolower((string) ini_get('display_errors')), ['1', 'on', 'yes', 'true'], true) ? 'Zapnuto v tomto požadavku' : 'Vypnuto v tomto požadavku',
            'WP_DEBUG_LOG' => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? 'Logování povoleno; zápis a přístupnost logu neověřeny' : 'Logování touto konstantou nepovoleno',
            'HTTPS' => is_ssl() ? 'Aktuální administrační požadavek používá HTTPS; certifikát zvenčí neověřen' : 'WordPress tento požadavek nerozpoznal jako HTTPS',
            'WP Cron' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? 'Spouštění návštěvami vypnuto; externí cron neověřen' : 'Spouštění návštěvami povoleno; vykonávání úloh neověřeno',
            'Cache aktualizací pluginů' => is_object($updates) && !empty($updates->last_checked) ? gmdate('c', (int) $updates->last_checked) : 'Čas nezjištěn',
            'XML-RPC přihlašované metody' => is_bool($auth) ? ($auth ? 'Lokální filtr je povoluje' : 'Lokální filtr je zakazuje') : 'Nejednoznačná hodnota filtru',
            'XML-RPC z internetu' => 'Neověřeno. Lokální filtr nekontroluje pingbacky, všechny metody ani firewall; výsledek se může lišit v XML-RPC požadavku.',
            'XML-RPC známé návaznosti' => $known !== [] ? implode(', ', $known) : 'Nenalezeny v omezeném seznamu (Jetpack); další pluginy, MU kód a externí klienti nejsou vyloučeni',
            'XML-RPC doporučení' => XmlRpcPolicy::advice($known, $externalUse, $auth),
        ];
        $checks = ['file_edit_disabled' => defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT === true, 'php_version' => PHP_VERSION, 'debug' => defined('WP_DEBUG') && (bool) WP_DEBUG, 'offers' => [
            'wordpress' => ['count' => count($coreOffers), 'at' => is_object($coreUpdates) ? (int) ($coreUpdates->last_checked ?? 0) : 0],
            'theme' => ['count' => count($themeOffers), 'at' => is_object($themeUpdates) ? (int) ($themeUpdates->last_checked ?? 0) : 0],
        ]];
        foreach (['plugins', 'woocommerce'] as $id) {
            $count = 0; foreach ($rows as $row) { if (($row['file'] === 'woocommerce/woocommerce.php') === ($id === 'woocommerce') && $row['update_version'] !== '') { ++$count; } }
            $checks['offers'][$id] = ['count' => $count, 'at' => is_object($updates) ? (int) ($updates->last_checked ?? 0) : 0];
        }
        return ['at' => gmdate('c'), 'source' => 'Lokální WordPress API a existující cache; žádné externí sondy', 'facts' => $facts, 'plugins' => $rows, 'checks' => $checks, 'automatic' => \JT\WPCare\Modules\Automatic\Evaluation::collect($checks, time())];
    }
}
