<?php
declare(strict_types=1);
namespace JT\WPCare\Modules\Checklist;

/** Read-only projection of the saved snapshot. Never overwrites an operator's notes. */
final class Evidence
{
    public static function forItem(string $id, ?array $audit): string
    {
        if ($audit === null) { return ''; }
        $map = [
            'diagnosis' => ['WordPress', 'PHP', 'Databáze', 'Šablona', 'WooCommerce'],
            'wordpress' => ['WordPress', 'Aktualizace WordPressu'],
            'php' => ['PHP'], 'theme' => ['Šablona', 'Parent / child', 'Aktualizace šablon'],
            'woocommerce' => ['WooCommerce'], 'wc_db' => ['WooCommerce DB'],
            'wordfence' => ['Wordfence'], 'two_factor' => ['2FA'], 'smtp' => ['SMTP'],
            'xmlrpc' => ['XML-RPC přihlašované metody', 'XML-RPC z internetu', 'XML-RPC známé návaznosti', 'XML-RPC doporučení'],
            'file_edit' => ['DISALLOW_FILE_EDIT'],
        ];
        $lines = [];
        foreach ($map[$id] ?? [] as $key) {
            if (isset($audit['facts'][$key]) && is_string($audit['facts'][$key])) { $lines[] = $key . ': ' . $audit['facts'][$key]; }
        }
        if ($id === 'plugins') {
            foreach ($audit['plugins'] ?? [] as $plugin) {
                $lines[] = $plugin['name'] . ' ' . $plugin['version'] . ' — ' . ($plugin['active'] ? 'aktivní' : 'neaktivní') . '; ' . $plugin['update'];
            }
            $lines[] = 'Čas cache: ' . ($audit['facts']['Cache aktualizací pluginů'] ?? 'Nezjištěn');
        }
        return implode("\n", $lines);
    }

    public static function instruction(string $id): string
    {
        return [
            'backup' => 'V hostingu ověř dokončení zálohy souborů i databáze a možnost obnovy. Napiš datum a místo zálohy.',
            'diagnosis' => 'Verze jsou doplněné níže. Dopiš známé závady a které důležité funkce jsi před servisem vyzkoušel.',
            'plan' => 'Napiš plánované zásahy a výjimky. Pořadí: WP → ostatní pluginy → šablony → WooCommerce → funkční test.',
            'wordpress' => 'Po případné aktualizaci spusť diagnostiku znovu a ověř přihlášení a administraci. Zapiš výsledek.',
            'php' => 'Ověř bezpečnostní podporu této větve na php.net/supported-versions.php a kompatibilitu webu. Doplň datum a výsledek.',
            'plugins' => 'Nabídky jsou z uložené cache, nikoli potvrzení aktuálnosti. Po aktualizacích ověř související funkce a spusť novou diagnostiku.',
            'theme' => 'Ověř vlastní úpravy a po aktualizaci vzhled hlavních stránek i mobilního zobrazení.',
            'woocommerce' => 'WooCommerce řeš až po ostatních aktualizacích. Ověř kompatibilitu plateb a dopravy; pokud e-shop není používán, zvol Netýká se a napiš důvod.',
            'wc_db' => 'Ve WooCommerce ověř dokončení požadovaných DB aktualizací a běžících úloh. Samotné číslo verze nestačí.',
            'gopay' => 'Pokud GoPay používáš, ověř průchod platbou a stav objednávky. Jinak zvol Netýká se a uveď používanou platební metodu.',
            'smtp' => 'Odešli test z používaného poštovního pluginu a ověř skutečné doručení. Napiš čas a výsledek bez adres zákazníků.',
            'order' => 'Proveď dohodnutou testovací objednávku: košík, pokladna, doprava, platba, stav objednávky a doručený e-mail.',
            'functions' => 'Vyzkoušej přihlášení, administraci, hlavní stránky a důležité formuláře. Zapiš, co fungovalo a případné chyby.',
            'wordfence' => 'V administraci Wordfence zkontroluj firewall, datum posledního scanu a nálezy. Aktivní plugin sám ochranu nepotvrzuje.',
            'two_factor' => 'Ověř zapnuté 2FA u administrátorů a zaznamenej případné výjimky. Nevkládej obnovovací kódy.',
            'xmlrpc' => 'Ověř externí použití a závislosti. Po případné změně otestuj přihlášení, administraci, objednávku a e-maily.',
            'file_edit' => 'Hodnotu konstanty ukazuje diagnostika. Pro Splněno může sloužit automatický důkaz Zapnuto; výjimku nebo jiné omezení popiš ručně.',
        ][$id] ?? '';
    }

    public static function proves(string $id, string $state, ?array $audit): bool
    {
        // Only this narrowly defined checklist item can be proved by a local value.
        return $id === 'file_edit' && $state === 'done' && ($audit['checks']['file_edit_disabled'] ?? null) === true;
    }
}
