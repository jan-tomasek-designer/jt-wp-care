<?php
declare(strict_types=1);
namespace JT\WPCare\Modules\Reports;

use JT\WPCare\Modules\Checklist\Catalog;
use JT\WPCare\Modules\Checklist\Evidence;

/** Both export formats use this exact text, never a second live audit. */
final class Formatter
{
    public static function text(array $record, string $site): string
    {
        $lines = ['JT WP Care ' . \JT\WPCare\Core\Release::VERSION . ' — průběžný servisní záznam', 'Web: ' . $site,
            'Metodika: ' . ($record['methodology'] ?? '0.1'), 'Revize: ' . $record['revision'],
            'Poslední uložení (UTC): ' . ($record['updated_at'] ?? 'Dosud neuloženo'),
            'Není potvrzením dokončeného servisu. Stavy checklistu zadal správce; diagnostika je automatický lokální snímek.', ''];
        foreach (Catalog::groups() as $group => $items) {
            $lines[] = $group;
            foreach ($items as $id => [$label, $hint]) {
                $row = $record['items'][$id] ?? ['state' => 'unknown', 'note' => ''];
                $lines[] = $label . ': ' . (Catalog::states()[$row['state']] ?? 'Neověřeno');
                $evidence = Evidence::forItem($id, $record['audit'] ?? null);
                if ($evidence !== '') { $lines[] = '  Zjištěno automaticky (' . ($record['audit']['at'] ?? '') . '): ' . $evidence; }
                $lines[] = '  Vlastní poznámka: ' . ($row['note'] !== '' ? $row['note'] : 'Bez ruční poznámky');
                if (!empty($row['at'])) { $lines[] = '  Záznam: ' . $row['at'] . ', správce ID ' . $row['by']; }
            }
            $lines[] = '';
        }
        $lines[] = 'Externí použití XML-RPC: ' . (['unknown' => 'Neověřeno', 'used' => 'Používá se', 'unused' => 'Správce potvrdil nepotřebnost'][$record['external_use'] ?? 'unknown'] ?? 'Neověřeno');
        $lines[] = $record['external_note'] ?? '';
        if (!empty($record['external_at'])) { $lines[] = 'Potvrzení: ' . $record['external_at'] . ', správce ID ' . $record['external_by']; }
        if (is_array($record['audit'] ?? null)) {
            $audit = $record['audit'];
            $lines[] = "\nDIAGNOSTIKA — " . $audit['at'];
            $lines[] = $audit['source'];
            foreach ($audit['facts'] as $label => $value) { $lines[] = $label . ': ' . $value; }
            $lines[] = "\nBĚŽNÉ PLUGINY";
            foreach ($audit['plugins'] as $plugin) {
                $lines[] = $plugin['name'] . ' ' . $plugin['version'] . ' | ' . ($plugin['active'] ? 'aktivní' : 'neaktivní') . ' | ' . $plugin['update'];
            }
        } else { $lines[] = 'Diagnostika dosud nebyla spuštěna.'; }
        return implode("\n", $lines) . "\n";
    }
}
