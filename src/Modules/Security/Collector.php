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
        $theme = wp_get_theme();
        $parent = $theme->parent();
        $auth = apply_filters('xmlrpc_enabled', true);
        $known = [];
        $rows = [];
        foreach ($plugins as $file => $data) {
            $active = is_plugin_active($file) || is_plugin_active_for_network($file);
            // Exact identifiers; no inference from translated display names.
            if ($active && $file === 'jetpack/jetpack.php') { $known[] = 'Jetpack'; }
            $offer = is_object($updates) && isset($updates->response[$file]) ? $updates->response[$file] : null;
            $latest = is_object($offer) && isset($offer->new_version) ? (string) $offer->new_version : '';
            $rows[] = ['name' => wp_strip_all_tags($data['Name']), 'version' => (string) $data['Version'], 'active' => $active,
                'update' => $latest !== '' ? 'Nabídka ' . $latest . ' (uložená cache)' : 'V uložené cache není nabídka; aktuálnost neověřena'];
        }
        if (defined('JETPACK__VERSION') && !in_array('Jetpack', $known, true)) { $known[] = 'Jetpack (načtený kód)'; }
        $wc = defined('WC_VERSION') ? (string) WC_VERSION : 'Nenačteno';
        $db = get_option('woocommerce_db_version', 'Nezjištěno');
        $facts = [
            'WordPress' => get_bloginfo('version'),
            'PHP' => PHP_VERSION . ' — bezpečnostní podporu ověřte ručně',
            'Databáze' => (string) $GLOBALS['wpdb']->db_version(),
            'Šablona' => $theme->get('Name') . ' ' . $theme->get('Version'),
            'Parent / child' => $parent ? 'Child theme; parent: ' . $parent->get('Name') . ' ' . $parent->get('Version') : 'Samostatná šablona',
            'WooCommerce' => $wc,
            'WooCommerce DB' => defined('WC_VERSION') ? (string) $db . ' — porovnání verzí samo nepotvrzuje dokončení migrací' : 'Netýká se / WooCommerce nenačteno',
            'Wordfence' => is_plugin_active('wordfence/wordfence.php') ? 'Aktivní plugin; firewall a scan nejsou ověřeny' : 'Aktivní běžný plugin nezjištěn; jiný způsob instalace neověřen',
            '2FA' => 'Pokrytí účtů neověřeno',
            'DISALLOW_FILE_EDIT' => defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT ? 'Zapnuto' : 'Není zapnuto touto konstantou; ostatní omezení neověřena',
            'WP_DEBUG' => defined('WP_DEBUG') && WP_DEBUG ? 'Zapnuto' : 'Vypnuto',
            'HTTPS' => is_ssl() ? 'Aktuální administrační požadavek používá HTTPS; certifikát zvenčí neověřen' : 'WordPress tento požadavek nerozpoznal jako HTTPS',
            'WP Cron' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? 'Spouštění návštěvami vypnuto; externí cron neověřen' : 'Spouštění návštěvami povoleno; vykonávání úloh neověřeno',
            'Cache aktualizací pluginů' => is_object($updates) && !empty($updates->last_checked) ? gmdate('c', (int) $updates->last_checked) : 'Čas nezjištěn',
            'XML-RPC přihlašované metody' => is_bool($auth) ? ($auth ? 'Lokální filtr je povoluje' : 'Lokální filtr je zakazuje') : 'Nejednoznačná hodnota filtru',
            'XML-RPC z internetu' => 'Neověřeno. Lokální filtr nekontroluje pingbacky, všechny metody ani firewall; výsledek se může lišit v XML-RPC požadavku.',
            'XML-RPC známé návaznosti' => $known !== [] ? implode(', ', $known) : 'Nenalezeny v omezeném seznamu (Jetpack); další pluginy, MU kód a externí klienti nejsou vyloučeni',
            'XML-RPC doporučení' => XmlRpcPolicy::advice($known, $externalUse, $auth),
        ];
        return ['at' => gmdate('c'), 'source' => 'Lokální WordPress API a existující cache; žádné externí sondy', 'facts' => $facts, 'plugins' => $rows];
    }
}
