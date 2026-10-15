# Automatické kontroly 0.5.0-alpha

Při otevření Nástroje → JT WP Care se diagnostika automaticky obnoví, pokud chybí, pochází ze staršího formátu nebo je starší než hodinu. Jde o jeden autentizovaný POST s nonce a oprávněním manage_options. Nejde o nepřetržitý monitoring ani naplánovanou práci bez návštěvy administrace. Na frontendu se nic nespouští. Bez JavaScriptu zůstává dostupná původní ruční diagnostika.

Samostatný modul automatic lze vypnout přes JT_WP_CARE_DISABLED_MODULES. Vypnutí security zablokuje i automatické sbírání. Automatické výsledky se při vypnutí těchto modulů nepoužívají jako náhrada ručních stavů.

## Co se vyhodnocuje

- Podpora větve PHP: aktivní / pouze bezpečnostní opravy / konec podpory. Tabulka ověřena 2026-10-12 na https://www.php.net/supported-versions.php, platná do 2027-04-12. Neznámá větev, předběžná verze nebo prošlá pravidla znamenají Neověřeno. Aktuálnost opravné verze a kompatibilita aplikace nejsou tímto testem doložené.
- Přesná hodnota DISALLOW_FILE_EDIT true.
- WP_DEBUG; diagnostika navíc rozlišuje WP_DEBUG_DISPLAY, PHP display_errors a WP_DEBUG_LOG bez zveřejnění cesty logu. Nejde o externí test úniku chyb.
- Nabídky aktualizací WP, ostatních pluginů, šablon a WooCommerce odděleně. Výsledek Vyžaduje pozornost znamená nabídku v cache do 24 hodin. Chybějící nabídka, stará nebo chybějící cache není potvrzení aktuálnosti. Cache se aktivně nestahuje.

V checklistu se PHP a DISALLOW_FILE_EDIT vyhodnocují bez ručního přepínače. Dřívější ruční záznamy a poznámky zůstávají zachované a zobrazené odděleně. Technický nález nikdy nepotvrzuje dokončení aktualizací, obnovitelnost zálohy, doručení pošty, funkci objednávky, 2FA ani Wordfence scan.

## Úložiště a bezpečnost

Automatické výsledky jsou novým volitelným polem audit.automatic ve stejném schématu 1. Snímek starší než 24 hodin se zobrazuje jako Neověřeno. UI a export odvozují stejné stavy ze stejného uloženého snímku; GET render nic nezapisuje. Automatický POST zachovává lidské položky a externí potvrzení XML-RPC. Souběh používá původní ochranu revizí.

Po dobu krátkého automatického požadavku jsou formuláře dočasně zakázané. Po uložení se stránka jednou obnoví; při chybě se formuláře zpřístupní a UI požádá o obnovení stránky. Timeout je 20 sekund bez automatického opakování. Server mohl při klientském timeoutu dokončit zápis; stará revize proto nesmí přepsat novější data.

Webový profil katalogu zatím není implementován. Ručně potvrzené Netýká se pro GoPay a objednávky na MRA se automatickým sběrem nezmění. Nic se samo neaktualizuje ani neodesílá e-maily.
