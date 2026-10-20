# JT WP Care 0.12.2-alpha

Opravné vydání řeší produkční případ z livemotion.cz. Pokud jsou `WP_DEBUG`, `WP_DEBUG_DISPLAY` a `WP_DEBUG_LOG` vypnuté, JT WP Care už nepovažuje odlišné mezery, uvozovky nebo velikost zápisu `false` za změnu vyžadující přepis `wp-config.php`. Bezpečný soubor proto nevyžaduje vytvoření záložní složky ani právo PHP k zápisu a na hostingu s omezenými právy se zobrazí „Oprava není potřeba“.

Ručně provedená změna `WP_DEBUG` nyní při otevření diagnostiky zneplatní starší uložený snímek. Automatická kontrola načte aktuální stav a odstraní staré varování bez čekání na běžný hodinový interval.

Řízený zápis zůstává dostupný jen tehdy, když je některá podporovaná konstanta skutečně aktivní a její zdroj lze bezpečně určit. Záloha, kontrola syntaxe, zámek, technický test a automatický návrat zůstávají beze změny.
