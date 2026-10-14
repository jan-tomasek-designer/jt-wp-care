<?php
declare(strict_types=1);
namespace JT\WPCare\Modules\Updates;

/** Removes one explicitly approved inactive plugin and records the technical post-test. */
final class Cleanup
{
    public const HISTORY = 'jt_wp_care_cleanup_history';

    public function remove(string $id, string $fingerprint): array
    {
        return (new Runner())->locked(function () use ($id, $fingerprint): array {
            $rows = Catalog::inactivePlugins();
            $row = $rows[$id] ?? null;
            if (!$row || !hash_equals($row['fingerprint'], $fingerprint) || $row['blocked'] !== '') {
                throw new \DomainException('Plugin se změnil, je aktivní nebo jej nelze bezpečně odstranit. Obnov seznam.');
            }
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            if (get_filesystem_method([], WP_PLUGIN_DIR . '/' . dirname($id)) !== 'direct') {
                throw new \DomainException('Hosting neumožňuje přímé odstranění souborů pluginu.');
            }
            $result = delete_plugins([$id]);
            wp_clean_plugins_cache(false);
            if (is_wp_error($result) || $result !== true || isset(get_plugins()[$id])) {
                throw new \DomainException('Soubory pluginu se nepodařilo úplně odstranit. Ověř stav přes správu pluginů nebo FTP.');
            }
            try { $verification = (new Verifier())->run(); }
            catch (\Throwable $error) {
                $verification = ['state' => 'unknown', 'at' => gmdate('c'),
                    'text' => 'Technický post-test se nepodařilo dokončit; ověř web ručně.', 'checks' => []];
            }
            $entry = ['id' => $id, 'name' => $row['name'], 'version' => $row['version'], 'at' => gmdate('c'),
                'by' => get_current_user_id(), 'verification' => $verification];
            $history = get_option(self::HISTORY, []);
            $history = is_array($history) ? $history : [];
            $history[] = $entry;
            update_option(self::HISTORY, array_slice($history, -20), false);
            return $entry;
        });
    }
}
