# Aktualizace JT WP Care přes WordPress

Samostatný běžný plugin **JT WP Care – instalátor aktualizací** aktualizuje verzovaný MU plugin. Instalátor 0.3.0-alpha načítá publikovaná testovací vydání z veřejného GitHub Releases, ověřuje manifest, očekávanou velikost a SHA-256 a teprve po potvrzení správce připraví novou složku a přepne ukazatel aktivní verze.

## První nasazení nebo výměna instalátoru

1. Ověř dokončenou zálohu databáze i souborů a možnost obnovy.
2. Z publikovaného vydání stáhni `jt-wp-care-installer-0.3.0-alpha.zip`.
3. Ve WordPressu otevři **Pluginy → Instalace pluginů → Nahrát plugin**.
4. Nahraď starší instalátor a aktivuj pouze jednu kopii ve složce `jt-wp-care-installer`.
5. Otevři **Nástroje → JT WP Care – aktualizace**.

## Běžná aktualizace MU pluginu

1. Zapni testovací vydání a klikni na kontrolu nové verze.
2. Zkontroluj nabízenou verzi a potvrď zálohu.
3. Spusť instalaci. Instalátor stáhne jen přesnou adresu assetu z ověřeného veřejného repozitáře.
4. Po přesměrování ověř aktivní i načtenou verzi.
5. V JT WP Care zkontroluj Přehled, obnov diagnostiku a proveď ruční test funkcí, které HTTP post-test nepokrývá.

## Hosting bez `disk_free_space`

Instalátor 0.3.0-alpha nejprve použije `disk_free_space`, pokud je dostupná. Když ji hosting zakazuje, vytvoří ve stejné instalační složce nulový kontrolní soubor o vypočtené potřebné velikosti, zápis potvrdí, soubor uzavře a odstraní. Teprve potom začne zapisovat nové vydání. Tím ověří skutečnou kvótu a oprávnění na stejném filesystemu bez odhadu z jiné dočasné složky.

Při nedostatku místa, chybě zápisu nebo přerušení se aktivní ukazatel nepřepne. Případný zbylý `.space-check` další pokus bezpečně odstraní pod instalačním zámkem. `flock`, přímý zápis, atomické přejmenování a podporovaný loader zůstávají povinné.

## Důvěra a limity

Repozitář a vydání musí být veřejné. SHA-256 potvrzuje shodu se zveřejněným manifestem, nejde o podpis nezávislého vydavatele. Instalace vyžaduje oprávnění správce, POST, nonce a výslovné potvrzení zálohy. Neběží bezobslužně.

Balíček může mít nejvýše 8 MiB, rozbalená data 32 MiB, jeden soubor 4 MiB a 1500 souborů. Instalátor odmítá cesty mimo vydání, odkazy, duplicity, nepodporované schéma a změněný loader. Původní vydání, servisní data a checklist zachovává.

## Návrat

Pokud administrace funguje, použij v instalátoru **Vrátit předchozí verzi**. Návrat nemění databázi. Při nedostupné administraci připni známé předchozí vydání přes `JT_WP_CARE_PINNED_RELEASE`, vypni framework konstantou `JT_WP_CARE_DISABLED`, nebo nouzově přejmenuj MU loader přes FTP.
