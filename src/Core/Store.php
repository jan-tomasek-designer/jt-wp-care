<?php
declare(strict_types=1);
namespace JT\WPCare\Core;

/** One per-site working record. Compare-and-swap prevents stale form overwrites. */
final class Store
{
    public const OPTION = 'jt_wp_care_record_v1';

    public function load(): array
    {
        $record = get_option(self::OPTION, null);
        if ($record === null) {
            return ['schema' => 1, 'revision' => '', 'methodology' => '0.1', 'items' => [], 'external_use' => 'unknown', 'external_note' => '', 'audit' => null];
        }
        if (!is_array($record) || ($record['schema'] ?? 0) !== 1 || !isset($record['revision'], $record['items']) || !is_array($record['items'])) {
            throw new \DomainException('Záznam má nepodporovaný formát. Nebyl přepsán.');
        }
        return $record;
    }

    public function save(array $record, string $expected): void
    {
        $old = $this->load();
        if (!hash_equals($old['revision'], $expected)) {
            throw new \DomainException('Záznam mezitím změnil jiný požadavek. Zkopírujte své poznámky, obnovte stránku a změny zadejte znovu.');
        }
        $record['schema'] = 1;
        $record['revision'] = wp_generate_uuid4();
        $record['updated_at'] = gmdate('c');
        $record['updated_by'] = get_current_user_id();
        if ($expected === '') {
            if (!add_option(self::OPTION, $record, '', false)) {
                throw new \DomainException('Záznam se nepodařilo založit nebo již existuje. Obnovte stránku.');
            }
            return;
        }
        global $wpdb;
        // Atomic update: two requests with the same old revision cannot both win.
        $affected = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s",
            maybe_serialize($record), self::OPTION, maybe_serialize($old)
        ));
        if ($affected !== 1) {
            throw new \DomainException('Záznam se neuložil: souběžná změna nebo chyba databáze. Obnovte stránku.');
        }
        wp_cache_delete(self::OPTION, 'options');
    }
}
