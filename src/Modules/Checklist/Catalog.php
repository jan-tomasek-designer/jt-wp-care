<?php
declare(strict_types=1);
namespace JT\WPCare\Modules\Checklist;

/** Stable IDs link evidence to the methodology, independent of UI labels. */
final class Catalog
{
    public static function groups(): array
    {
        return [
            'Před změnami' => [
                'backup' => ['Záloha existuje', 'Zdroj, čas, soubory + DB, dokončení a dostupnost obnovy. Test obnovy uveďte zvlášť.'],
                'diagnosis' => ['Výchozí technická diagnostika', 'Verze a základní technická dostupnost před servisem.'],
                'plan' => ['Plán servisu', 'Rozsah oprávněných zásahů, kompatibilita a pořadí.'],
            ],
            'WordPress' => [
                'wordpress' => ['Aktualizace WP', 'Aktuální stav, nebo verze před/po a ověření ruční aktualizace.'],
                'php' => ['PHP podporované', 'Verze, zdroj a datum ověření bezpečnostní podpory.'],
                'plugins' => ['Aktualizace pluginů', 'Ostatní pluginy; WooCommerce až po šablonách.'],
                'theme' => ['Aktualizace šablon', 'Parent/child, dostupné nabídky a výsledek.'],
            ],
            'WooCommerce a pošta' => [
                'woocommerce' => ['WooCommerce', 'Aktualizovat poslední v aktualizační části; zapsat výsledek.'],
                'wc_db' => ['DB update', 'Doložit dokončení nebo že aktualizace DB není potřeba.'],
                'gopay' => ['GoPay', 'Režim, průchod platbou, potvrzení a stav objednávky.'],
                'smtp' => ['SMTP test', 'Potvrzené přijetí testu, nejen odeslání. Platí i bez e-shopu.'],
                'order' => ['Order test', 'Košík, pokladna, stav objednávky a transakční e-mail.'],
            ],
            'Funkční test' => [
                'functions' => ['Technická dostupnost webu a administrace', 'Relace správce, administrace a omezený HTTP/HTML test základních stránek.'],
                'critical_functions' => ['Vizuální a kritické funkce', 'Vzhled, menu, formuláře a další důležité funkce, které nelze potvrdit HTTP kontrolou.'],
            ],
            'Security' => [
                'wordfence' => ['Wordfence', 'Stav ochrany, poslední scan a vyhodnocení nálezů. Instalace nestačí.'],
                'two_factor' => ['2FA', 'Pokrytí administrátorů a výjimky; bez obnovovacích kódů.'],
                'xmlrpc' => ['XML-RPC', 'Závislosti a externí využití; po změně přihlášení, administrace, WooCommerce a doručení e-mailů.'],
                'file_edit' => ['DISALLOW_FILE_EDIT', 'Ověřit true; výjimku zaznamenat jako odchylku.'],
            ],
        ];
    }

    public static function items(): array
    {
        return array_merge(...array_values(self::groups()));
    }

    public static function states(): array
    {
        return ['unknown' => 'Neověřeno', 'done' => '✓ Splněno', 'problem' => 'Problém', 'deferred' => 'Odloženo', 'na' => 'Netýká se'];
    }

    public static function normalize(array $input, array $old, ?array $audit = null): array
    {
        $items = [];
        foreach (self::items() as $id => $definition) {
            if (\JT\WPCare\Modules\Automatic\Evaluation::item($id, $audit) !== null) {
                $items[$id] = $old[$id] ?? ['state' => 'unknown', 'note' => '', 'at' => '', 'by' => 0];
                continue; // Automatic outcomes are derived; old human records remain untouched.
            }
            $row = $input[$id] ?? null;
            if (!is_array($row) || !isset($row['state'], $row['note']) || !is_string($row['state']) || !is_string($row['note']) || !isset(self::states()[$row['state']])) {
                throw new \DomainException('Neúplný formulář. Obnovte stránku a vyplňte všechna pole.');
            }
            if (strlen($row['note']) > 6000) {
                throw new \DomainException('Poznámka je příliš dlouhá (maximum 6000 bajtů).');
            }
            $note = trim(sanitize_textarea_field($row['note']));
            if ($row['state'] !== 'unknown' && $note === '' && !Evidence::proves($id, $row['state'], $audit)) {
                throw new \DomainException('Ke každému vyhodnocenému bodu doplňte důkaz nebo důvod: ' . $definition[0]);
            }
            $before = $old[$id] ?? [];
            $changed = ($before['state'] ?? 'unknown') !== $row['state'] || ($before['note'] ?? '') !== $note;
            $items[$id] = ['state' => $row['state'], 'note' => $note,
                'at' => $changed ? gmdate('c') : ($before['at'] ?? ''),
                'by' => $changed ? get_current_user_id() : ($before['by'] ?? 0)];
        }
        return $items;
    }
}
