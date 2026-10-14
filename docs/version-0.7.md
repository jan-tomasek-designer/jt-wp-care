# Verze 0.7: řízené aktualizace

## Rozhodnutí a kompatibilita

Samostatný modul `updates` používá standardní WordPress Plugin_Upgrader/Theme_Upgrader. Vlastní instalátor cizích pluginů by duplikoval kontrolu balíčků, filesystem a kompatibilitu. Pro plugin se používá jednopoložkové bulk API, protože single upgrade API plugin před aktualizací deaktivuje. Každá položka běží v novém autorizovaném POST; nové PHP se načte až v dalším požadavku. WP 6+/PHP 8+, bez WP-CLI, bez dalších závislostí. Multisite a jiné filesystem metody než direct jsou odmítnuty.

Schválení se vztahuje na přesný seznam, původní/cílovou verzi, aktivaci a hash URL balíčku z WordPressu. Změněná nabídka vyžaduje nový plán. Zdroje a kompatibilitu dodává aktualizační systém WordPressu; nabídka autora není zárukou kompatibility. Nepřepisujeme vlastní updater placené šablony, musí nabídnout platný balíček. Načtení nabídek je explicitní tlačítko, použitelné jsou nabídky do jedné hodiny.

Aktivní pluginy kromě Classic Editoru a aktivní parent šablona vyžadují samostatné potvrzení; WooCommerce vždy. Neaktivní pluginy/šablony stále vyžadují výběr a schválení celé dávky, nejsou označené za bezrizikové. Aktivní child theme a instalátor JT WP Care jsou z dávky vyloučené. Pořadí: pluginy → šablony → WooCommerce. WP core není součástí 0.7.

## Průběh a zotavení

1. Ověřit dokončenou zálohu souborů a DB a dostupnost obnovy.
2. Načíst nabídky, vybrat položky, potvrdit citlivé zásahy a připravit plán.
3. Spustit schválený seznam. Stránku nechat otevřenou, klient postupně odesílá jeden krok po druhém.
4. Před změnou se uloží stav running. Při fatální chybě, timeoutu či zavření stránky se nerozpracuje další položka a krok se neopakuje automaticky. V případě nejasného výsledku ověřit skutečný stav a uzavřít plán.
5. Kontroluje se skutečná verze souborů a aktivace pluginu, nikoli funkčnost webu. WooCommerce může samo spustit vlastní migrace. Ověřit katalog, detail, formuláře a relevantní funkce, poté exportovat report.

Souborový flock je sdílen s novým instalátorem JT WP Care. Chrání pouze operace Care. Nespouštět souběžně aktualizace z jiného panelu/FTP; běžící nativní automatické/core aktualizace a maintenance jsou kontrolovány, budoucí nativní auto-update se během ready/running plánu odmítne. Starý instalátor 0.1.2 tento společný zámek nezná.

Neexistuje univerzální automatický rollback databáze ani obnova po chybě nového PHP. Standardní WP rollback při chybě závisí na verzi WP a fázi selhání. Záloha je nutná. Historie posledních 20 plánů je oddělená v neautoloadovaných options, zachovává původní checklist a exportuje se do TXT/tisku. Uzavření nedokončeného plánu nepotvrzuje úspěch.

## JT WP Care z veřejného GitHubu

Nový běžný instalátor **0.2.0-alpha** je jednorázově potřeba nahrát přes Pluginy → Instalace pluginů a nahradit starý instalátor. MU payload samotný běžný instalátor nemění. Potom Nástroje → JT WP Care – aktualizace → Zkontrolovat novou verzi. Pro 0.x alpha je nutné zaškrtnout testovací vydání.

Pevný zdroj `jan-tomasek-designer/jt-wp-care`, žádný GitHub token, libovolná URL nebo main ZIP. Pouze publikované Releases s manifest.json a pojmenovaným payloadem; drafty a starší verze odmítá. Kontrola posledních 30 vydání, nové vydání se potvrzuje jednotlivě. Vydání a součet jsou připnuty na uživatele a 15 minut. Balíček se ověří podle velikosti a SHA-256, poté původní ZIP validací. Přesměrování pouze na povolené GitHub/CDN HTTPS domény, omezená velikost a timeout. Výpadek, rate limit nebo soukromý repozitář nemění běžící kód.

Důvěra je v konkrétní GitHub repozitář a HTTPS. SHA-256 není nezávislý podpis vydavatele; kompromitaci vydavatelského účtu neřeší. To odpovídá schvalovanému nasazení, nikoli bezobslužnému vzdálenému řízení. Návrat k předchozí verzi MU zůstává dostupný. Hosting bez `disk_free_space` vyžaduje podporovanou náhradní kontrolu kapacity.

## Vydání a stav veřejnosti

Kód updateru nezmění viditelnost repozitáře. V GitHub Settings je potřeba změnit repozitář na public a zveřejnit release `v0.7.0-alpha` s payload ZIPem, manifest.json, instalačním ZIPem, ZIPem instalátoru a SHA256SUMS.txt. Samotný commit ani Actions artifact nestačí. Připravit vydání z ověřeného commitu, nepřepisovat publikované balíčky. Při opravě vytvořit novou verzi.

Kontrola lokální historie nenalezla běžné vzory přístupových klíčů ani uložené klientské reporty/databáze. Historická dokumentace obsahuje domény pilotních webů a popis servisních zkoušek. Zveřejnění zpřístupní i tuto historii. Kontrola vzorů není úplný bezpečnostní audit.

## Ověření

Testy pokrývají pořadí, samostatné schválení, stale nabídky, expirované/cizí plány, souběh, nedostupný zápis, JS zastavení bez retry, výběr veřejného vydání, alpha opt-in, cizí URL/přesměrování, HTTP chyby, limity a poškozený hash. V místním skutečném WordPressu byla přes jeho upgrader aktualizována testovací aktivní komponenta a testovací šablona z 1.0.0 na 2.0.0; aktivace zůstala a maintenance se odstranila. Na klientském webu nebyly aktualizace touto verzí spuštěny. Veřejné stažení skutečného vydání je nutné ověřit po zveřejnění repozitáře/release.

Oficiální rozhraní: [Plugin_Upgrader::bulk_upgrade](https://developer.wordpress.org/reference/classes/plugin_upgrader/bulk_upgrade/) a [Theme_Upgrader::upgrade](https://developer.wordpress.org/reference/classes/theme_upgrader/upgrade/).
