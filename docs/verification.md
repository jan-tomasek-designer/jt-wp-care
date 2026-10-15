# Ověření 0.2.0-alpha

Datum: 2026-10-06. Balíček je určen pro pilot na stagingu.

## Provedeno

- PHP 8.3.35: kontrola syntaxe všech zdrojových PHP souborů.
- Smoke testy registru modulů a PSR-4 autoloadingu.
- 42 behaviorálních kontrol s náhradami WordPress API: povinné důkazy, neplatné a neúplné vstupy, maximální délka poznámek, zachování času nezměněného důkazu, XML-RPC rozhodování, oprávnění/POST/nonce, souběžné uložení, selhání DB, nepodporované schéma, escapování a report.
- Šest scénářů sestaveného MU loaderu: admin, frontend, úplné vypnutí, vypnutí všech modulů, starý WordPress, multisite.
- Skutečný lokální WordPress 7.1.2, PHP 8.3.35 a SQLite Database Integration: načtení MU balíčku, skutečné uložení a atomické porovnání záznamu, odmítnutí staré revize, vypnutý autoload, vygenerování administračních sekcí a bezpečné zobrazení poznámky.
- HTTP na skutečném lokálním WordPressu: autentizovaná stránka a 17 bodů, uložení a reload, stará revize HTTP 409, neplatný nonce HTTP 403, odmítnutí anonymního handleru, audit, TXT export, shodný text tiskové verze a odpověď 200 pro CSS balíčku.

Testovací WordPress běžel pouze lokálně, s umělými daty, blokovanými externími HTTP požadavky a potlačeným odesíláním pošty. Klientský web nebyl změněn. Použití SQLite je vlastnost testovacího prostředí, nikoliv závislost balíčku.

## Ještě ověřit před produkcí

- MySQL/MariaDB na cílovém hostingu; minimum WordPress 6.0 a další řady PHP. CI matice 8.0–8.5 je připravená, v této relaci nebyla celá spuštěna.
- S aktivním WooCommerce a konkrétní sestavou klientských pluginů; HPOS a checkout. Alpha nečte objednávky ani jejich tabulky.
- Skutečné bezpečnostní pluginy, přesměrování, cache a proxy hostingu.
- Vizuální kontrola administrační stránky a tiskového PDF v prohlížeči. Pokus o automatizované spuštění prohlížeče skončil chybou spawn EPERM, takže vizuální shodu nepotvrzujeme.

## Krátký staging postup

1. Nahrajte kompletní FTP balíček, loader poslední. Ověřte stránku Nástroje → JT WP Care.
2. Bez administrátorského oprávnění musí být stránka a operace nepřístupné.
3. Spusťte diagnostiku. XML-RPC z internetu musí zůstat neověřeno; žádný bod checklistu se automaticky nesplní.
4. Vyplňte důkaz zálohy, uložte a obnovte stránku. Ověřte zachování stavu i poznámky.
5. Ve dvou oknech upravte stejný záznam: druhé uložení zastaralého formuláře musí být odmítnuto.
6. Ověřte TXT a tiskovou verzi. PDF uložte přes prohlížeč a zkontrolujte češtinu, zalomení a úplnost.
7. Vyzkoušejte vypnutí security a reports a poté globální vypnutí. Po obnovení musí data zůstat zachovaná.
8. Teprve po úspěšném pilotu posuďte omezené produkční nasazení. Tento balíček není finální v1.0.

## Ověření 0.3.0-alpha (2026-10-07)

Lokálně PHP 8.3.35: syntaxe, smoke, 42 behavior kontrol, šest režimů loaderu, 15 scénářů verzovaného loaderu, kontrola obsahu a hashů ZIPů. Opakované balíčkování nad stejným buildem dalo stejné SHA-256; nesprávný tag byl odmítnut. Linux CI navíc kontroluje symlinkovaný ukazatel. Toto není test živého hostingu, atomického přepnutí ani skutečného vydání tagem. Pro tuto změnu nebyly opakovány integrační kontroly v reálném WordPressu; produkční nasazení vyžaduje staging.

## Ověření 0.4.0-alpha

Lokální PHP 8.3.35: původní behavior testy, nové předvyplnění/vykreslení/report, 50 kontrol balíčku a instalátoru a 14 kontrol oprávnění, nonce, metody požadavku a potvrzení. Linux CI přidává odmítnutí symlinkovaného ukazatele.

V místní skutečné instalaci WordPress 7.1.2 se SQLite adaptérem proběhlo přes HTTP s přihlášeným administrátorem: aktivace pomocného pluginu, multipart upload 0.4 payloadu z běžícího MU 0.3, přesměrování a ověření načtené nové verze, vykreslení asistovaného checklistu, návrat 0.4 → 0.3 a opětovný návrat 0.3 → 0.4. Uložený servisní záznam byl shodný před a po aktualizaci. To nepotvrzuje kompatibilitu konkrétního WooCommerce ani konkrétního hostingu. Zkouška nezahrnuje automatickou obnovu po fatální chybě ani GitHub download.

## Instalátor 0.1.1-alpha — hosting bez PHP ZIP

Příčina: instalátor 0.1.0 předpokládal dostupnost ZipArchive, která na jednom pilotním hostingu chyběla. Odmítnutí proběhlo před zápisem nové verze. Fallback podporuje pouze náš již používaný formát STORE, ne libovolnou kompresi. Lokálně ověřeno skutečným PHP -n bez ZipArchive: shodné vyčtené soubory, odmítnutí osmi poškozených/nebezpečných variant, instalace i návrat. Původních 50 kontrol nativního instalátoru nadále prochází. Aktualizační payload 0.4.0-alpha zůstává beze změny.

## Instalátor 0.1.2-alpha

Lokálně: původních 50 kontrol, cesta bez PHP zip s instalací a návratem, request boundary a nové kontroly bezpečných diagnostických zpráv. PHP -n s disable_functions=disk_free_space a samostatně flock ověřilo konkrétní odmítnutí před instalací. Neznámé runtime zprávy/cesty nejsou přenášeny do UI. Payload 0.4.0 má nadále SHA-256 5c44b750c7f64736f7b55bdbea3b114d468d4a63acb921b74ab76c3918dade6a. Hostitelská příčina selhání dosud není potvrzena.

## Automatika 0.5.0-alpha — 2026-10-12

Lokálně prošly testy podpory PHP včetně konce podpory a expirace pravidel, stáří cache/snímku, hodinového intervalu, zachování ručních položek a XML-RPC potvrzení, oprávnění a nonce. JavaScriptový test ověřil jeden POST, zákaz formulářů během požadavku, obnovení pouze při úspěchu a žádnou smyčku při chybě.

Ve skutečném místním WordPressu se ověřilo HTTP načtení stránky se starým snímkem, autentizovaný automatický POST, uložení výsledků, následná stránka bez dalšího požadavku, automatické stavy bez ručního PHP přepínače a zachování ručního formuláře pro zálohu. Uložené lidské položky a potvrzení XML-RPC se před/po shodovaly. Vzdálené produkční instalace při tomto testu nebyly měněny.
