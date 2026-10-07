# Protokol loaderu a balíčků

Loader má vlastní verzi 1.0.0 a podporuje protokol 1. Verzi aplikace a balíčku určuje src/Core/Release.php. Oddělení umožňuje měnit diagnostické moduly bez pravidelné výměny vstupního MU souboru.

current.json obsahuje schema: 1, active: číslo vydání a previous: číslo předchozího vydání nebo null. Každá verze má vlastní release.json s verzí, protokolem, datovým schématem a minimem PHP/WP. Loader akceptuje pouze protokol 1 a datové schéma 1. Nepřepisuje ukazatel ani uložená data.

Před načtením ověří metadata, platformu a dostupnost bootstrap.php a autoload.php. Pokud aktivní vydání těmito kontrolami neprojde, zkusí previous a zobrazí správci upozornění. Neprovádí úplnou kontrolu integrity všech souborů: poškození vnitřních tříd nebo runtime chyba proto vyžaduje ruční zásah. Dvě verze nikdy nenačte v jednom požadavku.

Při neplatném current.json se framework nespustí. Správce může před načtením WordPressu nastavit například:

```php
define('JT_WP_CARE_PINNED_RELEASE', '0.3.0-alpha');
```

Zadané vydání musí již kompletně existovat. Neplatný pin se nenahrazuje jinou verzí. Po opravě ukazatele pin odstraňte. Globální JT_WP_CARE_DISABLED má přednost. Symlinkované kořeny vydání, ukazatel, metadata a vstupní PHP soubory nejsou podporovány.

## Distribuce

Instalační ZIP obsahuje loader, current.json a jednu verzi. Payload ZIP obsahuje pouze adresář konkrétní verze: nemůže sám přepnout aktivní kód ani nahradit loader. Nepoužívejte instalační ZIP k přepsání běžící instalace. SHA256SUMS.txt ověřuje ZIPy i manifest; hash není podpis vydavatele.

Po tagu shodném s verzí v Release.php workflow spustí testy a vytvoří pouze draft GitHub Release. Publikování vyžaduje kontrolu správcem repozitáře. Release workflow dosud nebylo ověřeno skutečným tagem; běžné CI ověřuje build a obsah obou ZIPů.

Budoucí updater musí před instalací ověřit podpis, kompletní obsah, zápis, zámek a kompatibilitu. Teprve po přípravě nové složky smí atomicky přepnout ukazatel; na hostingu musí ověřit podporu této operace. Loader sám přepínání, health check ani automatický rollback po fatální chybě neřeší.
