# 0.4.0-alpha — 2026-10-07

- Automatické údaje a konkrétní ruční pokyny přímo u checklistu, zachování poznámek.
- Report obsahuje stejné automatické důkazy s časem snímku.
- Cache aktualizací WP/šablon a omezená detekce SMTP.
- Samostatný instalátor 0.1.0-alpha: potvrzené nahrání payload ZIPu přes WP, kontrola SHA-256, přepnutí a ruční návrat.
- Datové schéma 1 beze změny. Přímé stahování a podpisy nejsou implementovány.

# 0.3.0-alpha — 2026-10-07

- Stabilní MU loader, oddělené verzované složky a nouzový pin.
- Instalační a payload ZIP, nepodepsaný manifest a SHA-256.
- CI ověřuje protokol a balíčky; tag připraví draft vydání.
- Bez změny datového schématu; bez implementace updateru.

# Changelog

## 0.2.0-alpha — 2026-10-06

- První pilotní checklist se 17 body, důkazy, časy a ID správce.
- Uložení jednoho průběžného záznamu bez autoloadu, s atomickou ochranou před přepsáním souběžným požadavkem.
- Lokální diagnostický snímek na vyžádání: verze, šablona, pluginy a uložené nabídky aktualizací, základní bezpečnostní údaje.
- XML-RPC: oddělení přihlašovaných metod od dostupnosti z internetu, známá návaznost Jetpack a ruční potvrzení externího použití.
- TXT export a tisková HTML verze pro uložení PDF prohlížečem ze stejného uloženého záznamu.
- Moduly dashboard, checklist, security a reports lze nezávisle vypnout. Multisite alpha vědomě nespouští.
- Žádné automatické aktualizace, změny konfigurace, HTTP sondy ani odesílání e-mailů.

## Doplnění metodiky — 2026-10-06

- Převzato pravidlo XML-RPC: kontrola známých závislostí a potvrzení externího použití před doporučením vypnout.
- Doplněny povinné funkční testy po ruční změně a specifikace Security Audit.
- Bez změny PHP kódu a bez zásahu na klientském webu; výsledky skutečných testů nejsou tímto předáním doloženy.

## Změna směru — 2026-10-05

- Další vývoj pluginu odložen do ověření metodiky.
- Přidána pracovní metodika 0.1, checklist a TXT šablona reportu.
- Checklist přesunut do v1.0, výstupy určeny jako PDF/TXT.
- Existující PHP základ beze změny. Dokumenty nejsou záznamem provedeného servisu.

## 0.1.0-dev — 2026-10-05

- Počáteční architektura, roadmapa a servisní metodika.
- MU loader s kontrolou prostředí a nouzovým vypnutím.
- PSR-4 autoloading bez runtime balíčků.
- Modulární registr a základní administrační obrazovka s kontrolou oprávnění.
- FTP build, smoke testy jádra a konfigurace PHP CI matice.
- Diagnostika v1.0 a produkční integrační ověření dosud nejsou dokončené.
