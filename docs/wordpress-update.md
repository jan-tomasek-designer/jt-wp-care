# Aktualizace 0.3 → 0.4 přes WordPress

Verze 0.3 nemá updater. První přechod bez FTP proto zajišťuje samostatný běžný plugin **JT WP Care – instalátor aktualizací**. Zůstává oddělený od MU aplikace a umí se vrátit k předchozím souborům. Je volitelný; po ověření aktualizace jej lze deaktivovat. MU plugin dál běží.

## Postup

1. Ověř dokončenou zálohu databáze i souborů a dostupnost obnovy. Před produkcí vyzkoušej na testovací kopii.
2. Z úspěšného běhu CI pro schválený commit v repozitáři stáhni artifact `jt-wp-care-preview`. Rozbal pouze vnější ZIP. Uvnitř jsou balíčky a SHA256SUMS.txt.
3. Ve WordPressu otevři **Pluginy → Instalace pluginů → Nahrát plugin**. Vyber `jt-wp-care-installer-0.1.1-alpha.zip`, nainstaluj a aktivuj.
4. Otevři **Nástroje → JT WP Care – aktualizace**.
5. Vyplň `0.4.0-alpha`, vyber `jt-wp-care-0.4.0-alpha-payload.zip` a vlož 64znakový SHA-256 z odpovídajícího řádku SHA256SUMS.txt. Potvrď zálohu a aktualizaci.
6. Po přesměrování ověř aktivní i načtenou verzi. V JT WP Care ověř zachované poznámky, spusť diagnostiku, zkontroluj předvyplněné údaje a TXT/tiskový report. Vyzkoušej také důležité funkce webu.

Nepoužívej instalační MU ZIP ani zdrojový ZIP z tlačítka Code do běžného instalátoru WordPressu. Do „Pluginy → Nahrát plugin“ patří jen pomocný instalátor. Payload se nahrává až v jeho vlastní obrazovce.

## Důvěra a limity

Tento režim je **ruční nahrání balíčku z repozitáře**, nikoli přímé stahování GitHub → web. Nevyžaduje žádný GitHub token. Správce ověřuje zdroj ZIPu i součtu ve vlastním soukromém repozitáři. Kontrolní součet není kryptografický podpis; automatická distribuce vyžaduje navazující podepisování a samostatnou autentizaci. Do formuláře nelze vložit URL.

Instalátor vyžaduje manage_options i update_plugins, POST, nonce a potvrzení. Respektuje zákaz změn souborů a vypnutí frameworku; odmítá multisite a připnutou verzi. Vyžaduje přímý zápis a známý nezměněný loader. SSH/FTP filesystem transporty zatím nepodporuje. Na hostingu se nemění oprávnění ani limity.

Před zápisem ověří SHA-256, velikost, cesty, typy souborů, duplicity, platformu, protokol i datové schéma. Maximálně 8 MiB ZIP, 32 MiB rozbalených dat, 4 MiB na soubor a 1500 souborů. Zámek brání dvěma aktualizacím přes tento instalátor. Nové soubory vzniknou ve vlastní složce, pak se jedním rename ve stejné složce přepne JSON ukazatel. Přepnutí je určeno pro lokální filesystém s atomickým rename; chování síťového/distribuovaného filesystému a konkrétního hostingu musí ověřit staging. Současná ruční FTP změna není tímto zámkem chráněná.

Původní verze, loader a servisní option se nemění. Nové vydání se nepřepisuje, pokud už existuje. Při přerušení mohou zůstat pomocné soubory nebo kompletní neaktivní vydání; před opakováním ověř stav a nic naslepo nemaž. Instalační záznam v option jt_wp_care_last_update obsahuje poslední operaci, verze, UTC čas a ID správce. Nejde o úplnou historii servisů.

## Návrat

Pokud administrace funguje, v instalátoru potvrď **Vrátit předchozí verzi**. Návrat nemění databázi. Pokud nová verze způsobí fatální chybu před načtením administrace, tento pomocný plugin ji nemůže obejít: přidej ve wp-config.php JT_WP_CARE_PINNED_RELEASE se známou předchozí verzí, nebo vypni framework pomocí JT_WP_CARE_DISABLED. Nouzově přejmenuj MU loader přes FTP na jt-wp-care.php.disabled. Automatická obnova po runtime chybě zatím implementována není.

## Předvyplnění checklistu

Diagnostika u položek zobrazí **Zjištěno automaticky** s časem snímku. Vlastní poznámky zůstávají oddělené a nikdy se automaticky nepřepisují. Starší uložený snímek 0.3 je podporován; pro nové údaje spusť diagnostiku znovu. Informace o aktualizacích pocházejí z cache a jejich absence není potvrzení aktuálnosti. SMTP detekuje omezený seznam pluginů, nikoli doručení.

Stav funkčního ověření potvrzuje správce. U DISALLOW_FILE_EDIT může stav Splněno bez ruční poznámky podložit pouze nová diagnostika s přesnou hodnotou true. Ostatní dokončené body stále potřebují vlastní důkaz nebo důvod. Automatické údaje se objeví také v TXT a tiskovém reportu.


Instalátor 0.1.1 podporuje hosting bez PHP zip: omezený čteč přijímá pouze původní ZIPy typu STORE vytvořené naším buildem. Nic nedekomprimuje ani přímo nerozbaluje na disk. Ověřuje centrální i lokální hlavičky, souvislé umístění, CRC, typy a stejné následné bezpečnostní kontroly jako nativní čteč. Nepodporuje ZIP64, šifrování, kompresi a data descriptors; balíček proto nepřebalujte. PHP zip zůstává potřebné pouze při sestavování vydání.

## Instalátor 0.1.2-alpha: diagnostika selhání

Před další instalací otevři aktualizační obrazovku. Uvede verzi instalátoru i skutečnou složku, ze které běží. Kontroluje úplnost doplňkových souborů a dostupnost funkcí nutných k aktualizaci; při jejich zákazu instalaci zablokuje konkrétní zprávou. Tato kontrola sama neinstaluje vydání ani nepřepíná ukazatel. Kontrola filesystem metody WordPressu může vytvořit vlastní krátkodobý testovací soubor.

Při chybě zobrazí krok, bezpečný důvod a typ/soubor/řádek bez absolutních cest či obsahu runtime výjimky. Následně vždy ověř aktivní a načtenou verzi: selhání může nastat i po přepnutí při ukládání výsledku. Tato úprava zpřesňuje diagnostiku, nepotvrzuje dosud příčinu chyby na Livemotion. Při dvou instalátorech deaktivuj oba a aktivuj jen kompletní novou kopii 0.1.2. Veřejné zobrazování PHP chyb není potřeba.
