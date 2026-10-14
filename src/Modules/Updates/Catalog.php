<?php
declare(strict_types=1);
namespace JT\WPCare\Modules\Updates;

/** Only installed items and offers supplied by WordPress; never a URL from a form. */
final class Catalog
{
    /** Inactive plugins that an administrator may explicitly remove. Nothing is auto-deleted. */
    public static function inactivePlugins(): array
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $plugins = get_plugins();
        $required = [];
        foreach ($plugins as $file => $data) {
            if (!is_plugin_active($file)) { continue; }
            $slugs = preg_split('/\s*,\s*/', (string) ($data['RequiresPlugins'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
            foreach ($slugs ?: [] as $slug) { $required[$slug][] = wp_strip_all_tags((string) ($data['Name'] ?? $file)); }
        }
        $rows = [];
        foreach ($plugins as $file => $data) {
            if (is_plugin_active($file) || is_plugin_active_for_network($file)) { continue; }
            $slug = str_contains($file, '/') ? dirname($file) : basename($file, '.php');
            $blocked = '';
            if ($file === 'woocommerce/woocommerce.php') { $blocked = 'WooCommerce nelze odstranit tímto úklidem.'; }
            elseif (str_contains($file, 'jt-wp-care-installer')) { $blocked = 'Instalátor JT WP Care odstraň až po dokončení správy frameworku.'; }
            elseif (!empty($required[$slug])) { $blocked = 'Vyžaduje jej aktivní plugin: ' . implode(', ', $required[$slug]) . '.'; }
            $row = ['id' => $file, 'name' => wp_strip_all_tags((string) ($data['Name'] ?? $file)),
                'version' => (string) ($data['Version'] ?? ''), 'blocked' => $blocked];
            $row['fingerprint'] = hash('sha256', json_encode($row));
            $rows[$file] = $row;
        }
        uasort($rows, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));
        return $rows;
    }

    public static function offers(): array
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $out = [];
        foreach (['plugin', 'theme'] as $kind) {
            $cache = get_site_transient('update_' . ($kind === 'plugin' ? 'plugins' : 'themes'));
            if (!is_object($cache) || !is_array($cache->response ?? null) || empty($cache->last_checked)
                || (int) $cache->last_checked < time() - 3600 || (int) $cache->last_checked > time() + 300) { continue; }
            $installed = $kind === 'plugin' ? get_plugins() : wp_get_themes();
            foreach ($cache->response as $id => $raw) {
                if (!isset($installed[$id])) { continue; }
                $offer = (array) $raw;
                $before = $kind === 'plugin' ? $installed[$id]['Version'] : $installed[$id]->get('Version');
                $name = $kind === 'plugin' ? $installed[$id]['Name'] : $installed[$id]->get('Name');
                $to = $offer['new_version'] ?? '';
                $package = $offer['package'] ?? '';
                if (!is_string($to) || !preg_match('/^[0-9][a-zA-Z0-9.+_-]{0,79}$/D', $to)
                    || !version_compare($to, $before, '>') || !is_string($package) || !$package) { continue; }
                $reason = '';
                if (strtolower((string) parse_url($package, PHP_URL_SCHEME)) !== 'https' || !wp_http_validate_url($package)) { $reason = 'Balíček nemá platnou veřejnou HTTPS adresu.'; }
                if (!empty($offer['requires_php']) && version_compare(PHP_VERSION, (string) $offer['requires_php'], '<')) { $reason = 'Nová verze vyžaduje novější PHP.'; }
                if (!empty($offer['requires']) && version_compare((string) $GLOBALS['wp_version'], (string) $offer['requires'], '<')) { $reason = 'Nová verze vyžaduje novější WordPress.'; }
                if ($kind === 'theme' && $id === get_stylesheet() && get_stylesheet() !== get_template()) { $reason = 'Aktivní child theme může obsahovat vlastní úpravy; aktualizuj ji samostatně.'; }
                if ($kind === 'plugin' && str_contains($id, 'jt-wp-care-installer')) { $reason = 'Instalátor JT WP Care aktualizuj samostatně.'; }
                $active = $kind === 'plugin' ? is_plugin_active($id) : in_array($id, [get_template(), get_stylesheet()], true);
                $critical = $active && ($kind === 'theme' || $id !== 'classic-editor/classic-editor.php');
                if ($id === 'woocommerce/woocommerce.php') { $critical = true; }
                $row = ['kind' => $kind, 'id' => $id, 'name' => wp_strip_all_tags($name), 'from' => (string) $before,
                    'to' => $to, 'critical' => $critical, 'blocked' => $reason, 'active' => $active,
                    'package_hash' => hash('sha256', $package)];
                $row['fingerprint'] = hash('sha256', json_encode($row));
                $out[$kind . ':' . $id] = $row;
            }
        }
        return $out;
    }

    public static function select(array $input, array $approved, array $offers): array
    {
        if (!$input || count($input) > 30) { throw new \DomainException('Vyber 1 až 30 aktualizací.'); }
        $rows = [];
        foreach ($input as $key => $fingerprint) {
            $row = $offers[$key] ?? null;
            if (!$row || !is_string($fingerprint) || !hash_equals($row['fingerprint'], $fingerprint) || $row['blocked'] !== '') {
                throw new \DomainException('Nabídka aktualizací se změnila nebo není povolená. Načti nový seznam.');
            }
            if ($row['critical'] && ($approved[$key] ?? '') !== 'yes') { throw new \DomainException('Potvrď zvlášť každou citlivou aktualizaci.'); }
            $rows[] = $row;
        }
        // Stable grouping: plugins, themes, WooCommerce last. No core updates in this module.
        $rank = static fn(array $r): int => $r['id'] === 'woocommerce/woocommerce.php' ? 2 : ($r['kind'] === 'theme' ? 1 : 0);
        usort($rows, static fn(array $a, array $b): int => $rank($a) <=> $rank($b));
        return $rows;
    }
}
