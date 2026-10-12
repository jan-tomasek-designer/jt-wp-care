<?php
declare(strict_types=1);
namespace JT\WPCare\Modules\WooCommerce;

/** Bounded, read-only queries. Never collect action arguments or customer data. */
final class Inspector
{
    private function result(string $state, string $text): array { return compact('state', 'text'); }

    public function collect(int $now): array
    {
        if (!defined('WC_VERSION')) {
            return ['wc_database' => $this->result('unknown', 'WooCommerce není načteno; databáze neověřena.'),
                'wc_queue' => $this->result('unknown', 'WooCommerce není načteno; fronta neověřena.')];
        }
        $out = ['wc_database' => $this->result('unknown', 'Požadavek na aktualizaci databáze nelze zjistit.'),
            'wc_queue' => $this->result('unknown', 'Action Scheduler není připraven; fronta neověřena.')];
        try {
            $db = get_option('woocommerce_db_version', null);
            if (is_string($db) && preg_match('/^\d+\.\d+\.\d+$/D', $db) && is_callable(['WC_Install', 'needs_db_update'])) {
                $needed = \WC_Install::needs_db_update();
                if (is_bool($needed)) {
                    $out['wc_database'] = $this->result($needed ? 'problem' : 'unknown', $needed
                        ? 'WooCommerce požaduje aktualizaci databáze. Nic nebylo spuštěno.'
                        : 'WooCommerce nepožaduje aktualizaci databáze. Dokončení migrací a integrita dat tím nejsou potvrzené.');
                }
            }
        } catch (\Throwable $error) { /* Unavailable integration must not interrupt the other checks. */ }
        if (!function_exists('as_get_scheduled_actions') || !function_exists('did_action') || !did_action('action_scheduler_init')) { return $out; }
        try {
            // These queues belong to the whole site, including Woo extensions and other plugins.
            // Fetch at most one ID per query: existence is enough, no expensive exact counts.
            $failed = $this->exists(['status' => 'failed']);
            $late = $this->exists(['status' => 'pending', 'date' => $now - 3600, 'date_compare' => '<']);
            $migrationPending = $this->exists(['group' => 'woocommerce-db-updates', 'status' => 'pending']);
            $migrationRunning = $this->exists(['group' => 'woocommerce-db-updates', 'status' => 'in-progress']);
            $migrationFailed = $this->exists(['group' => 'woocommerce-db-updates', 'status' => 'failed']);
            if ($migrationPending || $migrationRunning || $migrationFailed) {
                $out['wc_database'] = $this->result('problem', 'Skupina migrací WooCommerce obsahuje '
                    . ($migrationFailed ? 'neúspěšnou úlohu (může být historická)' : 'čekající nebo běžící úlohu')
                    . '. Ověř stav v WooCommerce → Stav → Naplánované akce; úlohy nebyly spuštěny ani změněny.');
            }
            $out['wc_queue'] = $this->result($failed || $late ? 'problem' : 'unknown',
                'Action Scheduler celého webu: ' . ($failed ? 'nalezeny neúspěšné úlohy (včetně možných historických); ' : 'neúspěšné úlohy nenalezeny; ')
                . ($late ? 'nalezeny čekající úlohy opožděné přes hodinu. ' : 'čekající úlohy opožděné přes hodinu nenalezeny. ')
                . 'Nejde o důkaz funkčního cronu ani o určení příčiny. Ověř Naplánované akce.');
        } catch (\Throwable $error) {
            $out['wc_queue'] = $this->result('unknown', 'Čtení fronty se nezdařilo; ostatní diagnostika zůstává dostupná.');
        }
        return $out;
    }

    private function exists(array $query): bool
    {
        $ids = as_get_scheduled_actions($query + ['per_page' => 1, 'offset' => 0, 'orderby' => 'date', 'order' => 'ASC'], 'ids');
        if (!is_array($ids)) { throw new \RuntimeException('Unexpected queue result.'); }
        // Some database adapters report SQL failures without throwing an exception.
        if (!empty($GLOBALS['wpdb']->last_error)) { throw new \RuntimeException('Queue query failed.'); }
        return $ids !== [];
    }
}
