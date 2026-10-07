# Roadmapa a akceptace

## 0.1.0-dev — základ

Loader, autoloading, registr, vypínání, prázdná administrační obrazovka, build a testy jádra. Bez tvrzení o produkční připravenosti.

## Nejprve ověření metodiky

Uživatel 2026-10-06 potvrdil dokončenou zálohu a úspěšné funkční testy po vypnutí XML-RPC na obnovlimity.cz a zadal přípravu MU pluginu. Jde o uživatelské potvrzení konkrétního postupu, nikoli automatický důkaz všech bodů metodiky. Vývoj pokračuje pilotem 0.2.0-alpha; širší ověření metodiky a běžného webu zůstává otevřené.

## 0.2.0-alpha — pilot

Implementován jeden průběžný checklist, lokální diagnostika na vyžádání, omezená XML-RPC analýza, TXT a tisková verze pro PDF přes prohlížeč. Bez automatických zásahů. Historie, uzavírání servisů, plná diagnostika a přímé generování PDF nejsou dokončené. Multisite není podporováno. Stav ověření: [testy a staging](verification.md).

## 1.0 — checklist, diagnostické podklady a report

Priorita před širším nasazením: [distribuce a řízené aktualizace samotného JT WP Care](updates.md). Nejdříve centrální vydávání a lokálně potvrzený updater, potom centrální schválení pro vybrané weby. Tento mechanismus je oddělen od aktualizací klientského WordPressu a WooCommerce ve v2.0.

Základ: záloha, WP, PHP, pluginy, šablona; WooCommerce/DB, GoPay, SMTP a objednávka; Wordfence, 2FA, XML-RPC a DISALLOW_FILE_EDIT; PDF/TXT report. Evidovat ruční stav, důkazy, odložené i nerelevantní body. Uchovat rozpracovaný checklist a uzavřený záznam pro report. Aktualizace vykonává správce mimo plugin.

Následující tabulka zachovává diagnostický backlog, nikoli povinný rozsah první verze. Priority automatického ověřování vyplynou z pilotu metodiky.

Security Audit má rozpracovanou [specifikaci XML-RPC](security-audit-xmlrpc.md): detekce, známé závislosti, potvrzení externího použití, podmíněné doporučení a funkční ověření po ruční změně. Automatické vypnutí není součástí v1.0.

| Modul | Rozsah a hranice ověřování |
| --- | --- |
| Dashboard | WordPress, PHP, databázový engine/verze, server, aktivní šablona a parent/child vztah. Chybějící serverové údaje = unknown. |
| Plugins | Běžné, síťově aktivní a neaktivní pluginy, MU a drop-ins rozlišit. Dostupné aktualizace ze zdrojů WP s časem poslední kontroly. Starý výsledek není „aktuální“. |
| Plugins: zastaralost | Rozlišit dostupnou novou verzi, bezpečnostní podporu a dobu od vydání. Dlouho nevydaný plugin není automaticky nebezpečný. Privátní plugin bez metadat = unknown. Externí metadata načítat cíleně a cachovat. |
| WooCommerce | Přítomnost, aktivita, verze pluginu a DB, potřeba migrací dle podporovaného API, stav relevantních cron událostí a dostupné Action Scheduler fronty. Nic nespouštět ani neopravovat. Absence Woo = not_applicable. |
| SMTP | Detekce známých pluginů a dostupných konfigurací přes konkrétní adaptéry; pouze přítomnost nutných hodnot, žádné jejich zveřejnění. Potvrzený testovací e-mail jako samostatná akce. |
| Security | Wordfence přítomnost a dostupný stav, 2FA pokrytí jen při ověřitelném adaptéru, XML-RPC, REST API, DISALLOW_FILE_EDIT, počty administrátorů, debug/log/display, HTTPS a podpora PHP. HTTPS v aktuálním požadavku nepotvrzuje platnost certifikátu z internetu. |
| Performance | Nastavení WP Cron a opožděné události; externí cron nelze odvodit z DISABLE_WP_CRON. Rozlišit object cache, page cache a nepotvrzenou CDN. Uploads dávkově, velikost DB pouze v rozsahu tabulek webu a oprávnění. |
| Reports | Záznam servisu s časem, verzí metodiky, důkazy, neověřenými výsledky a doporučeními. PDF a TXT obsahují stejné údaje. |

Pořadí: ověření metodiky → checklist a evidence → PDF/TXT report → vybrané diagnostické podklady. Adaptéry nenutí instalaci konkrétního bezpečnostního nebo SMTP pluginu.

Podmínky vydání v1.0 (technické kontroly se vztahují na zařazené funkce):

- Ověřená metodika, doložené splnění bodů a viditelné neověřené stavy.
- Obnovení rozpracovaného servisu a shodný obsah PDF/TXT včetně výhrad.

- Testy oprávnění, přímých handlerů, CSRF, escapování a absence tajných údajů v reportech.
- Integrační testy na WordPress 6.0 a aktuální stabilní řadě v kompatibilních kombinacích PHP; přesná matice zapsaná k vydání.
- Web bez WooCommerce, podporované testované řady WooCommerce, HPOS zapnuto/vypnuto; diagnostika nečte objednávky přímo ze starých tabulek.
- Ověřené vypnutí frameworku a modulů, chybějící integrace, neúplná konfigurace a omezená DB oprávnění.
- Velké uploads a fronty, přerušení dávky, časové limity, odmítnutý externí požadavek a zastaralá cache.
- Kontrola sestaveného FTP balíčku na stagingu, absence automatických aktualizací, cronů a nevyžádaného e-mailu.
- Pilot na malém počtu webů před širším nasazením; zaznamenané verze a naměřené náklady auditu.

## 1.5 — rozšířená historie servisů

Checklist je už ve v1.0. V1.5 rozšíří procházení a porovnávání minulých servisů a opakované servisní cykly. Historie zůstane oddělená od aktuální diagnostiky; retence, přístup a migrace navážou na model první verze.

## 2.0 — řízené aktualizace

Náhled změn, upozornění a doporučení/ověření zálohy, kompatibilita a explicitní potvrzení. WordPress → běžné pluginy → šablony → WooCommerce. Souběžné spuštění blokované, při chybě zastavení a čitelný záznam. Chybějící informace o kompatibilitě se nesmí změnit na záruku bezpečnosti. Žádné vynucené automatické aktualizace.

## 2.5 — Wordfence integrace

Rozšířený adaptér pro firewall, stav a stáří malware scanu a login security. V1 pouze dostupná základní zjištění; v2.5 detailnější integrace ověřená pro konkrétní verze. Nedokumentované interní údaje nesmějí být vydávány za stabilní API. Neznámý stav zůstává unknown.

## 3.0 — fleet management

Centrální přehled webů, WordPress/PHP/Woo/plugin verzí, posledního servisu, zálohy a aktualizace. Před realizací samostatné rozhodnutí o centrální službě, autentizaci, rotaci/odvolání klíčů, podepisování zpráv a retenci. Preferovat odchozí přenos minimálního snapshotu, žádná vzdálená administrátorská oprávnění v prvním návrhu. Poslední známý stav uvádět s časem; zálohu bez potvrzeného zdroje označit neověřenou.
