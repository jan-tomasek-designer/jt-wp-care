<?php
declare(strict_types=1);
namespace JT\WPCare\Modules\Updates;

/** Each request changes one item. Persist running before invoking third-party upgrade hooks. */
final class Runner
{
    public const OPTION = 'jt_wp_care_update_plan';
    public const HISTORY = 'jt_wp_care_update_history';

    public function locked(callable $work)
    {
        $dir = WPMU_PLUGIN_DIR . '/jt-wp-care';
        $file = $dir . '/.maintenance.lock';
        if (!function_exists('flock') || is_link($dir) || is_link($file)) { throw new \DomainException('Nelze bezpečně zamknout aktualizace.'); }
        $lock = fopen($file, 'c');
        if (!$lock) { throw new \DomainException('Nelze zapsat zámek aktualizací.'); }
        if (!flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); throw new \DomainException('Aktualizace právě běží.'); }
        try { return $work(); } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    public static function save(array $plan): void
    {
        // Failure to persist must prevent starting another destructive step.
        update_option(self::OPTION, $plan, false);
        if (get_option(self::OPTION) !== $plan) { throw new \DomainException('Průběh aktualizace se nepodařilo uložit.'); }
        $history = get_option(self::HISTORY, []);
        $history = is_array($history) ? $history : [];
        $history[$plan['id']] = $plan;
        update_option(self::HISTORY, array_slice($history, -20, null, true), false);
    }

    public function step(string $id, int $index): array
    {
        return $this->locked(function () use ($id, $index): array {
            $plan = get_option(self::OPTION, []);
            if (($plan['id'] ?? '') !== $id || ($plan['by'] ?? 0) !== get_current_user_id()
                || ($plan['state'] ?? '') !== 'ready' || ($plan['index'] ?? -1) !== $index
                || time() - ($plan['created'] ?? 0) > 3600) { throw new \DomainException('Plán již nelze spustit. Obnov stránku a ověř stav; přerušené kroky neopakujeme automaticky.'); }
            $row = $plan['items'][$index];
            $plan['state'] = 'running'; $plan['updated'] = gmdate('c'); self::save($plan);
            try {
                $fresh = Catalog::offers()[$row['kind'] . ':' . $row['id']] ?? null;
                if (!$fresh || !hash_equals($row['fingerprint'], $fresh['fingerprint'])) { throw new \DomainException('Verze, nabídka nebo aktivace se změnila. Připrav nový plán.'); }
                $this->upgrade($row);
                $plan['results'][] = ['name' => $row['name'], 'from' => $row['from'], 'to' => $row['to'],
                    'state' => 'verifying', 'at' => gmdate('c'),
                    'message' => 'Verze souborů ověřena; čeká na technický post-test v novém požadavku.'];
                ++$plan['index'];
                // A fresh WordPress request must load the new plugin/theme code before functional checks.
                $plan['state'] = 'verifying';
            } catch (\Throwable $error) {
                $plan['state'] = 'stopped';
                $plan['results'][] = ['name' => $row['name'], 'from' => $row['from'], 'to' => $row['to'], 'state' => 'problem', 'at' => gmdate('c'),
                    'message' => $error instanceof \DomainException ? $error->getMessage() : 'Aktualizace selhala. Ověř skutečný stav webu a verzi; další položky se nespustily.'];
            }
            $plan['updated'] = gmdate('c'); self::save($plan);
            return $plan;
        });
    }

    public function verify(string $id, int $index): array
    {
        return $this->locked(function () use ($id, $index): array {
            $plan = get_option(self::OPTION, []);
            if (($plan['id'] ?? '') !== $id || ($plan['by'] ?? 0) !== get_current_user_id()
                || ($plan['state'] ?? '') !== 'verifying' || ($plan['index'] ?? -1) !== $index
                || $index < 1 || !isset($plan['results'][$index - 1])
                || time() - ($plan['created'] ?? 0) > 3600) {
                throw new \DomainException('Post-test již nelze spustit. Obnov stránku a ověř stav plánu.');
            }
            try { $verification = (new Verifier())->run(); }
            catch (\Throwable $error) {
                $verification = ['state' => 'unknown', 'at' => gmdate('c'),
                    'text' => 'Technický post-test se nepodařilo dokončit; před další změnou ověř web ručně.', 'checks' => []];
            }
            $result = &$plan['results'][$index - 1];
            $result['verification'] = $verification;
            $result['state'] = $verification['state'] === 'done' ? 'done' : 'problem';
            $result['message'] = 'Verze souborů ověřena. ' . $verification['text'];
            unset($result);
            // Unknown also stops: only a confirmed pass may release the next destructive step.
            $plan['state'] = $verification['state'] !== 'done' ? 'stopped'
                : ($index === count($plan['items']) ? 'complete' : 'ready');
            $plan['updated'] = gmdate('c'); self::save($plan);
            return $plan;
        });
    }

    private function upgrade(array $row): void
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        $context = $row['kind'] === 'plugin' ? WP_PLUGIN_DIR : get_theme_root($row['id']);
        if (get_filesystem_method([], $context) !== 'direct') { throw new \DomainException('Hosting neumožňuje přímý zápis. Použij správu aktualizací WordPressu.'); }
        if (get_option('auto_updater.lock') || get_option('core_updater.lock') || file_exists(ABSPATH . '.maintenance')) {
            throw new \DomainException('WordPress hlásí jinou aktualizaci nebo údržbu. Nejprve ověř její dokončení.');
        }
        $skin = new \WP_Ajax_Upgrader_Skin();
        $upgrader = $row['kind'] === 'plugin' ? new \Plugin_Upgrader($skin) : new \Theme_Upgrader($skin);
        // Standard upgrader handles extraction, compatibility and its supported rollback behavior.
        $pin = static function ($reply, $package) use ($row) {
            return is_string($package) && hash_equals($row['package_hash'], hash('sha256', $package))
                ? $reply : new \WP_Error('jt_changed_package', 'Balíček se změnil od schválení.');
        };
        add_filter('upgrader_pre_download', $pin, PHP_INT_MAX, 2);
        try {
            // Single-element bulk API preserves plugin activation; single upgrade() deactivates it.
            if ($row['kind'] === 'plugin') {
                $upgrader->bulk_upgrade([$row['id']], ['clear_update_cache' => false]);
            } else {
                $upgrader->upgrade($row['id'], ['clear_update_cache' => false]);
            }
        } finally { remove_filter('upgrader_pre_download', $pin, PHP_INT_MAX); }
        // Upgrader return values and skins may contain stale/non-fatal errors. The exact installed
        // version and preserved activation below are the authoritative postconditions.
        if ($row['kind'] === 'plugin') {
            wp_clean_plugins_cache(false);
            $plugins = get_plugins(); $actual = $plugins[$row['id']]['Version'] ?? '';
            if ($row['active'] !== is_plugin_active($row['id'])) { throw new \DomainException('Změnil se stav aktivace pluginu. Ověř jej ručně.'); }
        } else {
            wp_clean_themes_cache(false); $actual = wp_get_theme($row['id'])->get('Version');
        }
        if ($actual !== $row['to']) { throw new \DomainException('Nainstalovaná verze neodpovídá schválené nabídce. Ověř stav webu.'); }
    }
}
