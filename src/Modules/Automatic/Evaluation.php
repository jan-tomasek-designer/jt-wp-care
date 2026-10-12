<?php
declare(strict_types=1);
namespace JT\WPCare\Modules\Automatic;

/** Technical findings, deliberately separate from completed maintenance / functional tests. */
final class Evaluation
{
    public static function enabled(): bool
    {
        $disabled = defined('JT_WP_CARE_DISABLED_MODULES') ? JT_WP_CARE_DISABLED_MODULES : [];
        return !is_array($disabled) || (!in_array('automatic', $disabled, true) && !in_array('security', $disabled, true));
    }

    public static function php(string $version, int $now): array
    {
        // Source: https://www.php.net/supported-versions.php verified 2026-10-12.
        // Unknown branches and expired policy remain unknown, never guessed as supported.
        $dates = ['8.2' => ['2024-12-31', '2026-12-31'], '8.3' => ['2025-12-31', '2027-12-31'],
            '8.4' => ['2026-12-31', '2028-12-31'], '8.5' => ['2027-12-31', '2029-12-31']];
        if (!preg_match('/^(8\.[0-9]+)\.[0-9]+$/D', $version, $m) || !isset($dates[$m[1]])
            || $now < strtotime('2026-10-12 UTC') || $now >= strtotime('2027-04-12 UTC')) {
            return ['state' => 'unknown', 'text' => 'Podporu této větve nelze potvrdit z platných pravidel pluginu.'];
        }
        [$active, $security] = $dates[$m[1]];
        $supported = $now <= strtotime($security . ' 23:59:59 UTC');
        $type = $now <= strtotime($active . ' 23:59:59 UTC') ? 'aktivní podpora' : 'bezpečnostní opravy';
        return ['state' => $supported ? 'done' : 'problem', 'text' => 'PHP ' . $m[1] . ': ' . ($supported ? $type : 'podpora skončila')
            . '; bezpečnostní podpora do ' . $security . '. Zdroj: php.net/supported-versions.php (ověřeno 2026-10-12). Neověřuje aktuálnost opravné verze ani kompatibilitu webu.'];
    }

    public static function collect(array $checks, int $now): array
    {
        $out = ['php' => self::php((string) ($checks['php_version'] ?? ''), $now)];
        $value = $checks['file_edit_disabled'] ?? null;
        $out['file_edit'] = ['state' => $value === true ? 'done' : ($value === false ? 'problem' : 'unknown'),
            'text' => $value === true ? 'DISALLOW_FILE_EDIT je přesně true.' : 'DISALLOW_FILE_EDIT není potvrzeno jako true; jiné způsoby omezení nejsou ověřeny.'];
        $debug = $checks['debug'] ?? null;
        $out['debug'] = ['state' => $debug === false ? 'done' : ($debug === true ? 'problem' : 'unknown'),
            'text' => $debug === true ? 'WP_DEBUG je zapnutý. Zkontroluj účel a nastavení zobrazování/logování.' : ($debug === false ? 'WP_DEBUG je vypnutý.' : 'Stav debug režimu neověřen.')];
        foreach (['wordpress' => 'WordPress', 'plugins' => 'Ostatní pluginy', 'theme' => 'Šablony', 'woocommerce' => 'WooCommerce'] as $id => $label) {
            $offer = $checks['offers'][$id] ?? [];
            $fresh = is_int($offer['at'] ?? null) && $offer['at'] <= $now + 300 && $offer['at'] >= $now - 86400;
            $count = $offer['count'] ?? null;
            $out[$id] = ['state' => $fresh && is_int($count) && $count > 0 ? 'problem' : 'unknown',
                'text' => $label . ': ' . ($fresh && is_int($count) ? ($count > 0 ? $count . ' nabídek aktualizace v cache.' : 'V cache není nabídka; aktuálnost není potvrzena.') : 'Cache chybí nebo je starší než 24 hodin.')
                . ' Nejde o potvrzení provedené aktualizace.'];
        }
        return $out;
    }

    public static function results(?array $audit, ?int $now = null): array
    {
        if (!self::enabled() || !is_array($audit['automatic'] ?? null)) { return []; }
        $now = $now ?? time(); $at = strtotime($audit['at'] ?? '');
        $results = $audit['automatic'];
        $disabled = defined('JT_WP_CARE_DISABLED_MODULES') ? JT_WP_CARE_DISABLED_MODULES : [];
        if (is_array($disabled) && in_array('woocommerce', $disabled, true)) { unset($results['wc_database'], $results['wc_queue']); }
        if ($at === false || $at > $now + 300 || $at < $now - 86400) {
            foreach ($results as &$row) { $row = ['state' => 'unknown', 'text' => 'Snímek není čerstvý. Obnov diagnostiku.']; } unset($row);
        }
        return $results;
    }

    public static function item(string $id, ?array $audit): ?array
    {
        // Only checks whose full scope is machine-verifiable replace manual status controls.
        return in_array($id, ['php', 'file_edit'], true) ? (self::results($audit)[$id] ?? null) : null;
    }
}
