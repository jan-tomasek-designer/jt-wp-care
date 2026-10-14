# Servisní metodika JT WP Care

Verze 0.1 — pracovní návrh, aktualizace 2026-10-14. Při prvním pilotním servisu byla potvrzena dokončená záloha a úspěšné funkční testy po vypnutí XML-RPC. Jde o potvrzení tohoto konkrétního postupu, nikoli doložení všech bodů metodiky. Verze metodiky je nezávislá na verzi pluginu.

## Zásada

Nejdříve ověříme ruční servisní postup. Teprve z něj odvodíme plugin: checklist, diagnostické podklady, evidence výsledků a report PDF/TXT. Plugin v1.0 nebude provádět aktualizace.

## Význam stavů

Zaškrtnutí znamená doložené splnění podmínky, nikoli pouhé provedení pokusu.

| Stav | Význam |
| --- | --- |
| Neověřeno | Výchozí stav; kontrola nebo důkaz chybí. |
| Splněno | Podmínka ověřena, zapsán výsledek, čas a důkaz. Zobrazuje se ✓. |
| Problém | Kontrola nebo test odhalily nedostatek. |
| Odloženo | Uvést důvod, odpovědnost a další krok. |
| Netýká se | Pro tento web nerelevantní; uvést důvod. Nejde o splněný bod. |

Oddělit zjištěný stav od provedené práce: „WP byl aktuální“ a „WP aktualizován z A na B“ mohou uzavřít kontrolu, ale report musí rozlišit skutečně provedený zásah. Nezaškrtnutý bod musí mít vysvětlení.

## Zahájení servisu

Založit kopii checklistu. Uvést web, prostředí, technika, časové pásmo, servisní okno, oprávněný rozsah zásahů a kritické funkce. Hesla, tokeny a obnovovací kódy do záznamu nepatří.

## Pořadí a podmínky splnění

### 1. Záloha

Doložit čas, zdroj, rozsah (DB a potřebné soubory), úspěšné dokončení a dostupný postup/přístup k obnově. Záloha musí zachycovat stav před zásahy. U aktivního e-shopu posoudit objednávky přijaté po záloze.

„Záloha existuje“ není totéž jako „obnova otestována“. Test obnovy evidovat zvlášť s datem a výsledkem, nebo jako neprovedený. Bez doložené použitelné zálohy pokračovat pouze nezměnovou diagnostikou; aktualizace odložit.

### 2. Diagnostika

Zaznamenat výchozí verze WordPressu, PHP, pluginů, šablony a WooCommerce, existující závady a stav kritických funkcí. U PHP ověřit bezpečnostní podporu a zapsat zdroj a datum ověření. Změna PHP je samostatný plánovaný zásah.

### 3. Kontrola aktualizací

Sestavit plán cílových verzí, návazností a kompatibility. Zkontrolovat oprávněný rozsah práce. Nejasné nebo rizikové změny nejdříve ověřit na stagingu. Chybějící metadata nejsou důkazem aktuálnosti.

### 4.–7. Aktualizační část

Pořadí: WordPress → ostatní pluginy → šablony → WooCommerce. Správce vykonává zásahy ručně. WooCommerce vyjmout z hromadné aktualizace ostatních pluginů. Po každé skupině ověřit dostupnost webu a administrace; zaznamenat verze před/po a výsledek.

Závislosti WooCommerce rozšíření včetně platební brány posoudit už v plánu. Pokud pořadí vede k nekompatibilnímu mezistavu, dotčenou skupinu odložit a připravit postup ověřený na stagingu.

DB update WooCommerce evidovat samostatně: nebyl potřeba / potřeba a dokončen / neověřeno / problém. Pouhé spuštění migrace není dokončení. Zásah musí spadat do oprávněného rozsahu a mít ověřenou zálohu.

### 8. Funkční test

Ověřit hlavní stránky, administraci, relevantní formuláře a dohodnuté kritické funkce.

- GoPay: zapsat testovací/produkční režim a ověřit dohodnutý platební scénář včetně návratu, potvrzení platby a očekávaného stavu objednávky. Zobrazení brány nestačí. Bez provedené platby zaznamenat částečný rozsah a ponechat celý bod neověřený. Skutečná platba a refundace musí být předem dohodnuté.
- SMTP: test na dohodnutou adresu a potvrzené přijetí zprávy. Hlášení „odesláno“ nestačí. Kontrola platí i pro běžný web, pokud odesílá poštu.
- Order test: produkt → košík → pokladna → objednávka → očekávaný stav a transakční e-mail. Uvést bezpečný identifikátor testu a jeho výsledek.

Zapsat vypořádání testovacích objednávek a plateb; nepředpokládat automatické mazání nebo refundaci. Bez WooCommerce označit jeho body „netýká se“, bez GoPay totéž pro GoPay a uvést používanou bránu.

### 9. Bezpečnost

- Wordfence: přítomnost, aktivita, dostupný stav firewallu a datum/výsledek poslední kontroly; vyhodnotit otevřené nálezy. Pouhá instalace nestačí. Při jiné ochraně označit Wordfence „netýká se“ a alternativu prověřit zvlášť.
- 2FA: ověřit pokrytí administrátorských účtů a výjimky. Nezjistitelné pokrytí zůstává neověřené.
- XML-RPC: zjistit dostupný stav a prověřit aktivní pluginy na známé závislosti (například Jetpack), včetně síťově aktivních pluginů. Správce musí navíc potvrdit, zda používá vzdálené publikování nebo jiné externí integrace. Nenalezená známá závislost není důkaz nepotřebnosti. Vypnutí doporučit pouze podmíněně, pokud žádná známá závislost nalezena nebyla a správce potvrdil nepotřebnost. Při nejasnosti ponechat „neověřeno“. Žádné automatické vypnutí.
- DISALLOW_FILE_EDIT: ověřit hodnotu true. Schválenou výjimku uvést jako odchylku, nikoli splněný bod.

### Řízené opravy `wp-config.php`

Diagnostika a oprava jsou dva oddělené kroky. Plugin může nabídnout konkrétní opravu pouze po zjištění problému, nikdy ji nesmí provést při pouhém otevření stránky nebo při automatické diagnostice. Každý zásah vyžaduje oprávnění správce, ochranu proti podvrženému požadavku, nové výslovné schválení zásahu a potvrzení použitelné zálohy celého webu.

První povolená oprava vypíná pouze `WP_DEBUG`, `WP_DEBUG_DISPLAY` a `WP_DEBUG_LOG`. Hodnota `PHP display_errors` je samostatné serverové nastavení a nesmí se vydávat za opravené změnou těchto konstant. `SCRIPT_DEBUG`, vlastní logování aplikace a hostingová konfigurace nejsou součástí tohoto zásahu.

Před změnou musí nástroj najít skutečný `wp-config.php`, ověřit běžný soubor, právo číst a zapisovat, rozumnou velikost, platnou PHP syntaxi a jednoznačné literální definice. Dynamická definice, více definic, hodnota přivedená z prostředí, symbolický odkaz nebo nestandardní umístění znamenají ruční řešení. Nejasnou konfiguraci nástroj nesmí „opravit“ přidáním další definice.

Bezprostředně před zápisem vznikne datovaná chráněná záloha konfigurace s kontrolou integrity. Zápis proběhne pod zámkem a jen pokud se soubor od kontroly nezměnil. Po zápisu se ověří výsledný obsah a PHP syntaxe a proběhne technický test administrace a základních stránek. Při zjištěném problému se původní obsah automaticky vrátí, pokud se mezitím soubor znovu nezměnil. Neověřitelný HTTP test se zaznamená jako omezení a vyžaduje ruční kontrolu; není vydáván za úspěch.

Report eviduje čas, správce, rozsah opravy, výsledek technického testu, případný návrat a identifikátor zálohy bez zveřejnění jejího obsahu nebo absolutní cesty. Po opravě je nutné znovu načíst diagnostiku v novém požadavku, protože konstanty již načteného PHP procesu se za běhu nezmění.

Další diagnostika (SSL, debug, REST API, cron, cache, velikosti) zůstává v backlogu; rozšíření základního checklistu vyplyne z praxe.

Po ručním vypnutí XML-RPC zaznamenat způsob změny a možnost návratu, znovu ověřit stav XML-RPC a provést přihlášení, kontrolu administrace, WooCommerce a doručení e-mailů. Ověřit také relevantní externí integrace. Tyto následné testy patří k bezpečnostnímu zásahu i tehdy, pokud obecný funkční test už proběhl v kroku 8. Při selhání obnovit původní nastavení podle dohodnutého postupu a test zopakovat. Detail návrhu: [Security Audit — XML-RPC](security-audit-xmlrpc.md).

### 10. Report

TXT a PDF obsahují stejná fakta: výchozí a výsledný stav, provedené změny, důkazy testů, omezení, problémy a doporučení s odpovědností. Generování reportu neznamená automatické odeslání klientovi.

Výsledek servisu: dokončeno / dokončeno s výhradami / přerušeno. Dokončeno vyžaduje uzavřené relevantní body bez otevřených problémů. Odložené nebo neověřené body znamenají výhrady; blokující selhání přerušení. Vytvořené PDF samo nepotvrzuje úspěšný servis.

## Při chybě

Selhání zálohování (například timeout při kompresi) znamená stav „problém“ daného pokusu. Nepotvrzuje úspěšnou zálohu ani neprokazuje neexistenci jiné zálohy. Ověřit případnou jinou úplnou zálohu a dostupnost obnovy; do té doby odložit aktualizace a mazání. Chybová hláška sama neurčuje správné místo pro změnu serverového limitu.

Zastavit navazující změny, zaznamenat poslední úspěšný krok, čas a příznaky. Vyhodnotit dopad a zvolit opravu či obnovu podle dohodnutého plánu. Před obnovou DB e-shopu zohlednit nové objednávky a platby. Po nápravě opakovat dotčené testy a popsat incident v reportu.

## Ověření metodiky

Konkrétní servis vést v projektu daného webu. Sem předávat ověřené obecné poznatky: situace → příčina → řešení → důkaz → návrh pravidla. Sdílená dovednost bude distribuovat verzovanou metodiku; provozní stav klientských webů zůstává v servisních záznamech. Samotné použití dovednosti nenahrazuje předání aktuálního kontextu.

Použít checklist při servisu běžného webu a WooCommerce/GoPay e-shopu. Zaznamenat nejasnosti, chybějící kroky, potřebné důkazy a časovou náročnost. Po společném vyhodnocení upravit metodiku, označit ověřenou verzi 1.0 a až potom odvodit obrazovky a datový model pluginu.
