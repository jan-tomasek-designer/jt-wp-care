# Architektura a rozhodnutí

Stav: dlouhodobý návrh, částečně realizovaný pilotem 0.2.0-alpha po potvrzení postupu na prvním produkčním webu. Metodika má přednost. Pilot obsahuje checklist, lokální snímek a TXT/tiskový report; obecná rozhraní CheckInterface a AuditRunner popsaná níže zůstávají návrhem.

Pilotní rozhodnutí: ukládat jeden průběžný záznam jako option bez autoloadu. Ukládání používá atomické porovnání původní serializované hodnoty, aby souběžný požadavek nepřepsal novější důkazy. Databázové tabulky a archivace nejsou pro pilot potřeba. PDF vzniká přes tisk prohlížeče ze stejného textu jako TXT; nepřidáváme serverovou PDF závislost před ověřením šablony. Lokální sběr základní inventury je zatím součástí modulu Security, před rozšířením diagnostiky jej oddělíme podle kontraktů níže. Alpha neprovádí externí XML-RPC sondy a uvádí toto omezení u výsledku.

## 1. Distribuce MU pluginu

**Rozhodnutí:** malý loader v kořeni `mu-plugins`, zbytek v podadresáři. Zdrojový repozitář zůstává oddělen od sestaveného FTP balíčku.

**Důvod:** WordPress automaticky načítá PHP soubory pouze v kořeni MU adresáře. Přesouvání celého repozitáře na hosting by zveřejnilo nepotřebné soubory. Composer popisuje PSR-4 mapování, jednoduchý vlastní loader zajistí stejnou mapu v distribuovaném balíčku bez vendor závislostí. Aktivace MU pluginu nespouští activation hook.

**Dopad na kompatibilitu:** umístění loaderu a namespace `JT\WPCare` budou stabilní. Změna adresářů uvnitř modulu nemá být veřejným API. Budoucí migrace se nesmí spoléhat na aktivaci.

## 2. Malé jádro a explicitní moduly

**Rozhodnutí:** jádro zná pouze `ModuleInterface` s `id()` a `register()`. Bootstrap explicitně sestavuje moduly; žádné procházení adresářů ani reflexní hledání služeb. Závislosti předávat konstruktorem.

**Důvod:** pro desítky instalací je důležitá předvídatelnost a snadné dohledání toho, co se spouští. Není potřeba DI kontejner. Konstruktory nic neskenují a nic nezapisují. `register()` pouze připojuje hooky.

**Dopad:** ID modulu je stabilní veřejný identifikátor. Nový modul má vlastní adresář, registraci a testy. Zakázaný modul nesmí registrovat hooky ani spouštět kontroly. Kontroly nesmějí záviset na zapnutém dashboardu. Chyba při samotném auditu bude převedena na chybový výsledek, nikoliv na úspěch; obecné potlačování všech PHP chyb není bezpečnostní izolace.

Plán struktury modulu: `Module.php`, `Checks/`, případně `Adapters/`, `Admin/`. Nemusí mít všechny složky. WooCommerce a Wordfence budou volitelné adaptéry s kontrolou dostupnosti funkcí a verzí.

## 3. Auditní kontrakt a reporty

**Rozhodnutí:** před implementací kontrol zavést `CheckInterface`, `CheckResult`, `AuditRunner` a snapshot auditu. Kontrola vrátí stabilní ID, kategorii, stav, závažnost, čas UTC, zdroj dat, stáří dat, stručný důkaz a doporučení. Stav: `ok`, `warning`, `critical`, `unknown`, `not_applicable`, `error`; závažnost nesmí nahrazovat informaci, zda kontrola skutečně proběhla.

**Důvod:** administrace i reporty musí zobrazovat stejná zjištění. Neznámý výsledek nesmí vypadat jako úspěšná kontrola. Oddělení výsledků od HTML dovolí pozdější export i fleet management bez přepisování kontrol.

**Dopad:** report ponese `schema_version`, verzi frameworku i metodiky, čas a rozsah kontroly. Historické snapshoty se zpětně nepřepočítávají. Uživatelské výstupy v1.0 jsou PDF a TXT se stejnými údaji; způsob tvorby PDF určí ověřená šablona. Export pouze autentizovanou cestou s oprávněním, bez veřejných souborů v uploads. Ruční stav servisního kroku oddělit od automatického nálezu; konkrétní model odvodit až po pilotu metodiky.

## 4. Načítání a výkon

**Rozhodnutí:** integrace sestavit na `plugins_loaded`, první UI pouze v administraci. Audit pouštět na vyžádání, nikoliv při každém otevření administrace nebo návštěvě webu.

**Důvod:** běžné pluginy musí být načtené před jejich detekcí. Velikost uploads, databáze či seznam front může na velkém webu překročit limity hostingu.

**Dopad:** náročné kontroly dostanou dávky, kurzor, časový limit, možnost pokračování a stav „částečné“. Respektovat hranice uploads, nenásledovat symlinky ven, nečíst obsah souborů. Výchozí limit jedné dávky navrhnout konzervativně a změřit na stagingu. V1 nebude závislá na funkčním WP Cronu; pokračování může řídit administrační požadavek. Cache výsledků bude mít TTL a viditelný čas pořízení.

## 5. Bezpečnost a hranice auditu

**Rozhodnutí:** přístup přes capabilities, pro v1 `manage_options`; u každého handleru kontrola oprávnění znovu. Všechny spouštěné operace včetně e-mailu a výpočtů přes POST s nonce; nonce sám není autorizace. Vstupy validovat, výstupy escapovat podle kontextu, SQL parametrizovat.

**Důvod:** audit zpřístupňuje interní informace. Přístup do menu nestačí k ochraně exportu nebo AJAX handleru.

**Dopad:** žádné SMTP heslo, token, obsah `wp-config.php`, osobní e-maily administrátorů ani plné serverové cesty v běžném reportu. Žádná veřejná REST diagnostika. Žádná telemetrie ve v1. Aktivní HTTP sondy musí mít konkrétní povolený cíl, krátký timeout a ochranu před SSRF; detekce bez sondy musí uvést omezení. Aktivní REST API či XML-RPC automaticky neznamená zranitelnost.

SMTP test je výslovná výjimka z čistě pasivního auditu: uživatel zadá příjemce a potvrdí odeslání; nonce, capability a omezení četnosti. Úspěch `wp_mail()` znamená předání zprávy, nikoli doručení. Žádné automatické testovací zprávy.

## 6. Úložiště a budoucí akce

**Rozhodnutí:** v1 uchová rozpracovaný checklist a uzavřený záznam pro report. Úložiště a retenci navrhnout podle pilotu metodiky, v1.5 rozšíří práci s historií. Nastavení v namespacovaných options bez autoloadu, dočasná data s expirací; bezpečnost exportu nezávisí na domnělé neveřejnosti úložiště. Samostatné tabulky posoudit podle výsledného datového modelu.

**Důvod:** objemná historie nepatří do automaticky načítaných options. Pro každý web musí být jasné, co se uchovává a jak dlouho.

**Dopad:** schéma bude mít vlastní verzi. Migrace explicitní, idempotentní, s blokováním souběhu a možností přerušení. Žádné automatické destruktivní změny při načtení MU pluginu. Multisite vyžaduje izolaci per web a odlišná network oprávnění; není implicitně podporováno v0.1.

Ve v2 oddělit auditní `Check` od měnícího `Action`. Akce vyžaduje potvrzený plán, kontrolu aktuálního stavu, zámek, log a opakovatelnou validaci oprávnění. Použít aktualizační API WordPressu. Záloha musí mít doložený stav, pouhé upozornění není ověření existence zálohy. WooCommerce přeskočit ve skupině běžných pluginů a zařadit poslední v aktualizační části, včetně samostatné kontroly migrací. Rollback souborů nevrací změny databáze.

## 7. Zpětná kompatibilita

Od v1.0 zveřejnit seznam stabilních rozhraní, hooků, ID a schémat. Interní třídy nejsou veřejným API. Nová volitelná pole musí mít výchozí hodnoty; odstranění veřejného prvku předchází deprecace alespoň v jedné minor řadě a nastane v major vydání. Kompatibilitu ověřovat proti konkrétním verzím WordPressu a WooCommerce, nezaměňovat syntaktickou podporu PHP za otestovanou platformu.

## Zdroje

- [WordPress MU plugins](https://developer.wordpress.org/advanced-administration/plugins/mu-plugins/)
- [WordPress capabilities](https://developer.wordpress.org/plugins/security/checking-user-capabilities/)
- [WordPress security](https://developer.wordpress.org/apis/security/)
- [PHP podporované verze](https://www.php.net/supported-versions.php)

## 0.3.0: verzovaný loader

Implementace rozděluje stabilní loader a aplikaci ve složce releases/<verze>. Důvodem je příprava nové verze vedle běžícího kódu. Ukazatel je JSON, nikoli spustitelné PHP. Protokol loaderu a datové schéma jsou verzované odděleně; tento loader připouští pouze hodnotu 1. Podrobnosti a omezení obnovy obsahuje [protokol](loader-protocol.md). Přechod z plochého rozložení 0.2.0 je jednorázový ruční krok. Implementace sama nic nestahuje ani nepřepíná.

## 0.4.0: asistovaný checklist a první aktualizace bez FTP

Automatické důkazy jsou projekcí uloženého snímku, ne přepisem poznámek. Proto zůstanou uživatelská data kompatibilní se schématem 1 a export používá stejný zdroj jako obrazovka. Stav testu potvrzuje člověk, s úzkou výjimkou doložení DISALLOW_FILE_EDIT přes typovanou hodnotu true.

Pomocný běžný plugin v installer/ má oddělený namespace a lifecycle. Důvodem je bootstrap aktualizací u již nasazené verze 0.3, která nemá updater, a možnost vrátit soubory nezávisle na UI MU modulu. Je volitelný a deaktivovatelný. Kvůli pořadí načítání MU pluginů však neobejde fatální chybu při bootstrapu; nouzová obnova zůstává přes loader/pin/FTP. Ruční upload ověřený správcem a SHA-256 je mezikrok; automatická distribuce nadále vyžaduje podpisy.

## 0.5.0: automatické technické výsledky

Samostatný vypínatelný modul automatic spouští při návštěvě administrace omezený POST sběr. UI GET ani frontend neprovádějí zápis. Výsledky jsou ve volitelném poli snímku audit.automatic; schema zůstává 1. Zachování původních lidských stavů umožní návrat ke staršímu kódu bez migrace dat. Pouze dva úzce definované technické body nahrazují v UI ruční volbu; širší servisní úkony mají automatické nálezy oddělené od potvrzení provedení. Při neaktuálním snímku/pravidlech se nepoužívá zelený stav z minulosti.
