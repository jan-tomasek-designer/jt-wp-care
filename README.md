# JT WP Care 0.12.0-alpha

První instalovatelná pilotní verze MU pluginu. **Určena pro staging, nikoli plošné produkční nasazení.** Metodika vychází z ručního servisu; modul updates provádí schválené aktualizace pluginů a šablon. WordPress core a změny zabezpečení zůstávají ruční.

## Co balíček umí

- Nástroje → JT WP Care: 17bodový checklist se stavy neověřeno, splněno, problém, odloženo a netýká se.
- Přehled a samostatné záložky Checklist, Diagnostika a bezpečnost, Aktualizace a Report.
- Uloží jeden průběžný záznam; u vyhodnoceného bodu vyžaduje důkaz nebo důvod. Zaznamenává UTC čas změny a ID správce.
- Při otevření administrace automaticky (nejvýše jednou za hodinu) nebo na vyžádání pořídí lokální diagnostický snímek: WordPress, PHP, DB, šablona/child theme, běžné pluginy, uložené nabídky aktualizací, WooCommerce a základní bezpečnostní informace.
- Security Audit rozlišuje XML-RPC přihlašované metody od neověřené dostupnosti z internetu; hledá Jetpack a vyžádá potvrzení externího použití.
- TXT export a tisková HTML verze používají tentýž uložený záznam. PDF vzniká přes Tisk → Uložit jako PDF v prohlížeči, nejde o serverový PDF generátor.
- Oprávnění manage_options, POST + nonce u handlerů a ochrana před přepsáním záznamu souběžným požadavkem.
- Řízené aktualizace po každé položce ověří přihlášenou relaci, administraci a HTTP/HTML dostupnost základních stránek; při nejasném nebo chybném výsledku zastaví další kroky.
- Úklid zobrazí neaktivní pluginy a po dvojím potvrzení odstraní vždy jen jeden vybraný plugin. Aktivní pluginy, WooCommerce, instalátor JT WP Care a rozpoznané závislosti aktivních pluginů jsou chráněné; po odstranění proběhne technický post-test.
- Nástěnka barevně rozlišuje dokončené, čekající a problémové oblasti. HTTP test rozpozná také běžné texty fatální PHP chyby, chyby databázového spojení a režimu údržby vrácené se stavem 200.
- V diagnostice nabízí první řízenou opravu konfigurace: po dvojím potvrzení vypne `WP_DEBUG`, `WP_DEBUG_DISPLAY` a `WP_DEBUG_LOG`. Před zápisem vytvoří chráněnou zálohu, odmítne nejednoznačný `wp-config.php`, ověří syntaxi a po zásahu provede technický test s návratem při zjištěném problému.

## Omezení alpha

Jeden rozpracovaný servis bez archivace, uzavírání a zakládání dalšího cyklu. Report je průběžný záznam, nikoli potvrzení dokončení. Modul Security obsahuje zatím i základní lokální inventuru; plný diagnostický systém je backlog. XML-RPC používá omezený seznam známých závislostí (Jetpack), není externě sondováno. PHP support, SMTP doručení, 2FA pokrytí, firewall/scan, GoPay, objednávka a dokončení migrací vyžadují ruční ověření. Seznam běžných pluginů neobsahuje MU a drop-ins. Diagnostický sběr sám cache neobnovuje; tlačítko Načíst aktuální nabídky ji obnoví a chybějící nabídka není důkaz aktuálnosti.

Multisite je v této verzi výslovně blokované. Základní kód cílí na PHP 8.0+ a WordPress 6.0+; konkrétní provedené testy a zbývající staging kontroly uvádí [ověření](docs/verification.md).

## Aktualizace existující instalace přes WordPress

Použij [návod k pomocnému instalátoru](docs/wordpress-update.md). Instalátor 0.4.0-alpha načte testovací vydání z veřejného GitHub Releases, ověří manifest a SHA-256 a po potvrzení správce přepne verzi bez ručního nahrávání ZIPu. Na hostingu bez `disk_free_space` použije omezený kontrolní zápis na stejném disku. Rozpracovanou operaci zapíše před stažením; po ručním obnovení proto ukáže, zda se verze opravdu přepnula, i když prohlížeč na pomalém hostingu přestal čekat.

## První instalace přes FTP

První MU instalace se provádí přes FTP. Další potvrzené aktualizace umí běžný instalátor stáhnout z veřejného GitHub Releases. Níže je postup prvního nasazení.

1. Použijte staging a zálohu. Rozbalte distribuční ZIP lokálně.
2. Nahrajte adresář `jt-wp-care` do `wp-content/mu-plugins/`.
3. Nahrajte `jt-wp-care.php` do stejného adresáře jako poslední.
4. Přihlaste se jako správce a otevřete **Nástroje → JT WP Care**. MU plugin se neaktivuje v seznamu běžných pluginů.
5. Spusťte diagnostiku, vyplňte důkazy a uložte checklist. Před jinou akcí na stránce uložte rozpracovaný formulář.
6. Ověřte TXT a tiskový náhled. Stará stránka po uložení v jiném okně vyžaduje obnovení.

```text
wp-content/mu-plugins/
├── jt-wp-care.php
└── jt-wp-care/
    ├── current.json
    └── releases/0.12.0-alpha/
        ├── release.json
        ├── bootstrap.php
        ├── autoload.php
        ├── src/
        └── assets/
```

Do hostingu nenahrávejte repozitář, testy ani dokumentaci. Při další výměně nejdříve vypněte framework, uchovejte předchozí balíček, nahrajte kompletní nový obsah a znovu zapněte. FTP výměna více souborů není atomická.

## Přechod z 0.2.0

Jednorázově vypněte JT WP Care konstantou níže, uchovejte původní složku i loader mimo veřejný web a nasaďte celý nový instalační balíček. Potom konstantu odeberte a ověřte diagnostiku i uložený checklist. WordPress option se nemění. Původní plochý balíček 0.2.0 není automaticky použitelný jako previous. Nouzový návrat vyžaduje obnovení jeho původní složky i loaderu při vypnutém frameworku.

[Protokol loaderu a postup obnovy](docs/loader-protocol.md).

## Vypnutí

Před načtením WordPressu ve `wp-config.php`:

```php
define('JT_WP_CARE_DISABLED', true);
// Nebo konkrétní moduly:
define('JT_WP_CARE_DISABLED_MODULES', ['security', 'reports']);
```

ID modulů: `dashboard`, `checklist`, `security`, `reports`, `automatic`, `functional-tests`, `woocommerce`, `updates`, `repairs`. [Automatické kontroly a jejich omezení](docs/automatic-checks.md). Vypnutý modul neregistruje UI ani své handlery. Vypnutí dashboardu skryje stránku; ostatní moduly mají vlastní registraci, pro kompletní vypnutí použijte globální konstantu. Nouzově přes FTP přejmenujte loader tak, aby neměl příponu `.php`.

## Uložená data

WordPress option `jt_wp_care_record_v1` bez autoloadu: stavy, poznámky, ID správců, UTC časy, revize, potvrzení XML-RPC a poslední snímjspěšnou zálohu ani neprokazuje neexistenci jiné zálohy. Ověřit případnou jinou úplnou zálohu a dostupnost obnovy; do té doby odložit aktualizace a mazání. Chybová hláška sama neurčuje správné místo pro změnu serverového limitu.

Zastavit navazující změny, zaznamenat poslední úspěšný krok, čas a příznaky. Vyhodnotit dopad a zvolit opravu či obnovu podle dohodnutého plánu. Před obnovou DB e-shopu zohlednit nové objednávky a platby. Po nápravě opakovat dotčené testy a popsat incident v reportu.

## Ověření metodiky

Konkrétní servis vést v projektu daného webu. Sem předávat ověřené obecné poznatky: situace → příčina → řešení → důkaz → návrh pravidla. Sdílená dovednost bude distribuovat verzovanou metodiku; provozní stav klientských webů zůstává v servisních záznamech. Samotné použití dovednosti nenahrazuje předání aktuálního kontextu.

Použít checklist při servisu běžného webu a WooCommerce/GoPay e-shopu. Zaznamenat nejasnosti, chybějící kroky, potřebné důkazy a časovou náročnost. Po společném vyhodnocení upravit metodiku, označit ověřenou verzi 1.0 a až potom odvodit obrazovky a datový model pluginu.
