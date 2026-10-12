# JT WP Care 0.7.0-alpha

První instalovatelná pilotní verze MU pluginu. **Určena pro staging, nikoli plošné produkční nasazení.** Metodika vychází z ručního servisu; modul updates provádí schválené aktualizace pluginů a šablon. WordPress core a změny zabezpečení zůstávají ruční.

## Co balíček umí

- Nástroje → JT WP Care: 17bodový checklist se stavy neověřeno, splněno, problém, odloženo a netýká se.
- Uloží jeden průběžný záznam; u vyhodnoceného bodu vyžaduje důkaz nebo důvod. Zaznamenává UTC čas změny a ID správce.
- Při otevření administrace automaticky (nejvýše jednou za hodinu) nebo na vyžádání pořídí lokální diagnostický snímek: WordPress, PHP, DB, šablona/child theme, běžné pluginy, uložené nabídky aktualizací, WooCommerce a základní bezpečnostní informace.
- Security Audit rozlišuje XML-RPC přihlašované metody od neověřené dostupnosti z internetu; hledá Jetpack a vyžádá potvrzení externího použití.
- TXT export a tisková HTML verze používají tentýž uložený záznam. PDF vzniká přes Tisk → Uložit jako PDF v prohlížeči, nejde o serverový PDF generátor.
- Oprávnění manage_options, POST + nonce u handlerů a ochrana před přepsáním záznamu souběžným požadavkem.

## Omezení alpha

Jeden rozpracovaný servis bez archivace, uzavírání a zakládání dalšího cyklu. Report je průběžný záznam, nikoli potvrzení dokončení. Modul Security obsahuje zatím i základní lokální inventuru; plný diagnostický systém je backlog. XML-RPC používá omezený seznam známých závislostí (Jetpack), není externě sondováno. PHP support, SMTP doručení, 2FA pokrytí, firewall/scan, GoPay, objednávka a dokončení migrací vyžadují ruční ověření. Seznam běžných pluginů neobsahuje MU a drop-ins. Diagnostický sběr sám cache neobnovuje; tlačítko Načíst aktuální nabídky ji obnoví a chybějící nabídka není důkaz aktuálnosti.

Multisite je v této verzi výslovně blokované. Základní kód cílí na PHP 8.0+ a WordPress 6.0+; konkrétní provedené testy a zbývající staging kontroly uvádí [ověření](docs/verification.md).

## Aktualizace existující instalace 0.3 přes WordPress

Použij [návod k pomocnému instalátoru](docs/wordpress-update.md). Umožňuje ručně nahrát payload ZIP z repozitáře a potvrdit změnu bez FTP. Nejde zatím o přímé stahování ani automatické aktualizace.

## První instalace přes FTP

Pro běžné budoucí aktualizace připravujeme [centrální distribuci a potvrzený updater](docs/updates.md). Pomocný instalátor již umí potvrzené nahrání payloadu přes WordPress. Přímé stahování a automatická distribuce zatím implementované nejsou. Níže je postup první MU instalace.

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
    └── releases/0.7.0-alpha/
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

ID modulů: `dashboard`, `checklist`, `security`, `reports`, `automatic`. [Automatické kontroly a jejich omezení](docs/automatic-checks.md). Vypnutý modul neregistruje UI ani své handlery. Vypnutí dashboardu skryje stránku; ostatní moduly mají vlastní registraci, pro kompletní vypnutí použijte globální konstantu. Nouzově přes FTP přejmenujte loader tak, aby neměl příponu `.php`.

## Uložená data

WordPress option `jt_wp_care_record_v1` bez autoloadu: stavy, poznámky, ID správců, UTC časy, revize, potvrzení XML-RPC a poslední snímek. Záznam zůstává až do ručního odstranění správcem; alpha nemá retenční automatizaci. Vypnutí/odstranění souborů nic nemaže. Samotné vykreslení nic neukládá; při otevření JT WP Care může automatický modul samostatným ověřeným POST požadavkem obnovit diagnostický snímek. Exporty se streamují do prohlížeče, nevznikají veřejné soubory v uploads. Do poznámek nevkládejte hesla, klíče ani osobní údaje zákazníků.

## Vývoj a sestavení

Bez runtime závislostí, Composer ani WP-CLI nejsou na hostingu potřeba.

```sh
php tests/smoke.php
php tests/behavior.php
php tools/build.php
php tests/versioned-loader.php
php tools/package.php
php tests/package.php
```

Build vytvoří `dist/mu-plugins/`. Existující dist odmítne přepsat; před dalším buildem jej přesuňte. PSR-4 namespace je `JT\WPCare`, zdroje jsou v `src/`. Složky modulů obsahují vlastní registraci a služby; Core řeší lifecycle, request boundary a úložiště. Konfigurace CI připravuje PHP 8.0–8.5; její existence není důkazem provedení této matice.

[Metodika](docs/service-methodology.md) · [Ruční checklist](docs/service-checklist.md) · [XML-RPC specifikace](docs/security-audit-xmlrpc.md) · [Architektura](docs/architecture.md) · [Roadmapa](docs/roadmap.md) · [Changelog](CHANGELOG.md)

SemVer; před 1.0 jsou kontrakty experimentální. Datové schéma je verzováno odděleně, nepodporované schéma se nepřepisuje. [GitHub repozitář](https://github.com/jan-tomasek-designer/jt-wp-care), bez udělené open-source licence; veřejná distribuce vyžaduje zveřejnění repozitáře a vydání. Balíčkování vyžaduje rozšíření PHP zip pouze na vývojovém stroji/CI. Tag odpovídající verzi připraví draft vydání; publikování zůstává ruční.

WooCommerce DB a fronta úloh: viz [popis kontrol](docs/woocommerce-audit.md). Modul: `woocommerce`.

## Aktualizace 0.7

[Řízené aktualizace a veřejný GitHub](docs/version-0.7.md) jsou popsány v docs/version-0.7.md. Modul `updates` lze vypnout. Instalátor 0.2.0-alpha stahuje vydání přímo z GitHubu; starší instalátor je potřeba jednou nahradit přes nahrání běžného pluginu.
