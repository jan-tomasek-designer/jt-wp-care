# Distribuce a řízené aktualizace JT WP Care

Stav 2026-10-07: v 0.3.0-alpha je implementován verzovaný loader, instalační/payload ZIP a workflow pro draft vydání. Podpisy, distribuce a updater zůstávají návrhem; instalace je stále ruční. Podrobnosti: [protokol loaderu](loader-protocol.md). Aktualizace samotného JT WP Care je samostatná funkce; nenahrazuje plánované aktualizace WordPressu, pluginů a WooCommerce ve v2.0.

## Cíl

Jedno místo pro vydání a následně i schválení nasazení. Běžné aktualizace bez opakovaného FTP, s explicitním potvrzením konkrétní verze a cílových webů. Výpadek distribuční služby nesmí zastavit běžící web ani diagnostiku.

## Rozdělení odpovědností

- Soukromý GitHub repozitář: zdrojový kód, metodika, testy, historie změn a release workflow. Žádná klientská data nebo přístupové údaje.
- Vydání: neměnný ZIP, číslo verze, changelog, požadavky PHP/WP, podporované datové schéma a protokol loaderu, velikost, SHA-256 a kryptograficky podepsaný manifest. Vydání vychází z konkrétního tagu a commitu; nikdy se neinstaluje pohyblivý obsah main.
- Distribuční služba: zpřístupní schválené manifesty a balíčky registrovaným webům. Přístup k privátnímu GitHubu zůstává pouze zde. Klientské weby nedostanou společný GitHub osobní token.
- Web: ověří původ a kompatibilitu a vykoná pouze schválenou aktualizaci JT WP Care.
- Centrální přehled: později umožní schválit stejnou verzi pro vybrané weby, sledovat skutečné výsledky a zastavit další nasazování při selhání.

Důvod: oddělení zdrojového repozitáře, distribuce a oprávnění webů umožní odvolat jeden napadený web bez výměny přístupu na všech ostatních. Hash sám nepotvrzuje důvěryhodného vydavatele, proto je součástí návrhu podpis.

## Stabilní MU loader a verze vedle sebe

Před širším nasazením upravit současné rozložení: malý stabilní loader, nezávislá obslužná část aktualizace a adresáře jednotlivých verzí. Nová verze se připraví vedle aktivní. Každý požadavek si na začátku zvolí jednu verzi a její autoloader; za běhu ji nemíchá s jinou.

Aktivní verzi určuje malý validovaný ukazatel. Přepnutí musí být atomické na konkrétním filesystému a otestované včetně OPcache. Pokud použitá filesystem metoda atomické přepnutí nezaručí, tento režim aktualizace se nepovolí. Nepoužívat nahrazování souborů aktivní verze jednotlivě ani vyhodnocování PHP z manifestu.

Starou verzi zachovat pro návrat. Opravy stabilního loaderu jsou zvláštní vydání s vlastní kontrolou kompatibility a záchranným postupem. FTP zůstává nouzovou cestou, nikoli plánovanou rutinní obsluhou.

## Průběh jedné aktualizace

1. Získat a ověřit podepsanou informaci o vydání. Kontrola dostupnosti může být periodická a cachovaná, nesmí sama instalovat.
2. Zobrazit změny, požadavky, případné migrace a možnost návratu. Správce schválí konkrétní verzi; místně capability a nonce, centrálně autentizovaný omezený příkaz.
3. Ověřit oprávnění zápisu, volné místo, dostupné prostředky, kompatibilitu a stav zálohy odpovídající plánovaným změnám. Respektovat DISALLOW_FILE_MODS a globální vypnutí JT WP Care.
4. Získat zámek pro konkrétní web a vydání. Duplicitní spuštění nesmí vést ke dvěma současným aktualizacím.
5. Stáhnout pouze z povoleného HTTPS zdroje s limitem velikosti, timeoutem a kontrolou přesměrování. Ověřit podpis manifestu, verzi, hash a velikost ZIPu před rozbalením. Podpisové klíče mají plán rotace a odvolání; starší vydání se nesmí vnutit jako nové.
6. Rozbalit do izolovaného adresáře, odmítnout cesty mimo něj, symlinky, neočekávaný obsah a nadměrnou rozbalenou velikost. Nepovolit libovolnou URL nebo příkaz z centrály.
7. Ověřit připravenost balíčku, pak přepnout aktivní verzi. Nikdy nemažte původní verzi před dokončením.
8. V odděleném požadavku ověřit načtení správné verze a základní funkce. Kontrola má omezený, autentizovaný rozsah. Při selhání obslužná část nezávislá na novém kódu vrátí předchozí ukazatel; po fatální chybě nenačítat obě verze do téhož PHP požadavku.
9. Zapsat skutečný výsledek, čas, verzi před/po a případný návrat. Nedostupná kontrola znamená neověřeno, nikoli úspěch. Opakované selhání nesmí vyvolat nekonečnou smyčku instalací.

Jednotlivé fáze musí přežít timeout nebo přerušení požadavku. Pro každý přerušený stav definovat pokračování či bezpečný návrat, expiraci zámku a identifikátor operace.

## Data a návrat

Aktualizační balíček nemá obsahovat servisní data. Návrat souborů nevrací databázi. První updater povolí pouze vydání se zpětně kompatibilním datovým schématem. Destruktivní migrace nebudou součástí běžné aktualizace jedním kliknutím; potřebují samostatný plán obnovy a potvrzení. Staré verze se uklidí až po ověření, se zachováním poslední ověřené verze.

## Centrální schválení

Každý web má vlastní odvolatelný přístup a identitu. Web si úlohu vyzvedává odchozím připojením; centrála nemá obecný příkazový přístup do WordPressu. Úloha určuje pouze konkrétní podepsané vydání JT WP Care, cílový web, platnost a jedinečné ID; chránit proti opakovanému použití a schválení jiné verze.

Nasazovat po skupinách: staging → pilotní web → další vybrané weby. Chyba zastaví další skupinu. Reportovat odlišně čekající, stažené, instalované, ověřené, vrácené a offline weby. WP Cron nezaručuje přesný čas; vyzvedávání musí podporovat ruční spuštění a volitelný externí scheduler bez WP-CLI. Centrální zásah vyžaduje předchozí přihlášení webu do tohoto režimu.

## Postup implementace

1. GitHub a reprodukovatelná vydání s podpisy, stabilní loader a balíčkový protokol.
2. Modul Updates: kontrola vydání a potvrzená aktualizace v administraci jednoho webu; stejné jádro později použije centrála.
3. Malý centrální přehled pro distribuci samotného JT WP Care. Není nutné čekat na celý fleet management v3.0.

Před vydáním otestovat chybný podpis/hash, nedostatek místa, zamítnutý zápis, paralelní požadavky, ZIP traversal, přerušení každé fáze, nekompatibilní PHP/schéma, OPcache, nedostupnou centrálu a fatální chybu nové verze. Zkoušky na MySQL/MariaDB a cílovém hostingu jsou povinné před produkčním pilotem.

## Omezení hostingu a zdroje

MU pluginy nemají běžný aktualizační tok standardních pluginů: [WordPress MU plugins](https://developer.wordpress.org/advanced-administration/plugins/mu-plugins/). Vlastní aktualizační vrstva je nutná pro zachování MU architektury.

WordPress Filesystem API může podle oprávnění potřebovat přístupové údaje: [Filesystem API](https://developer.wordpress.org/apis/filesystem/). Nelze slíbit bezobslužný zápis na každém hostingu. Na CZECHIA nejprve ověřit zápis a přepnutí na stagingu; při nesplnění podmínek nabídnout srozumitelný důvod a nouzový ruční postup, nikoli měnit oprávnění plošně.
