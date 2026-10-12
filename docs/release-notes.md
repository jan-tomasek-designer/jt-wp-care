# JT WP Care 0.7.0-alpha

Řízené aktualizace pluginů a šablon: načíst nabídky, vybrat verze, potvrdit zálohu a citlivé změny, spustit seznam. Plugin aktualizuje postupně a při chybě zastaví. WooCommerce poslední; aktivní child theme se tímto postupem nepřepisuje. Historie výsledků je v reportu. Funkční test a dostupná obnova zůstávají nutné.

Instalátor 0.2.0-alpha umí stáhnout publikované vydání JT WP Care z veřejného GitHubu a sám ověřit SHA-256. Není potřeba ručně nahrávat payload ani zadávat hash. Starý instalátor je nutné jednou nahradit ZIPem běžného pluginu. Pro alpha vydání zaškrtnout testovací kanál. Repozitář i release musí být veřejně dostupné; tento text sám nepotvrzuje jejich publikaci.

Loader a datové schéma zůstávají 1. Historie aktualizací používá samostatné options a zachovává checklist. Nové operace jsou jen po potvrzení, nikoli na pozadí. Platí limity direct filesystem a disk_free_space pro instalátor JT WP Care. V případě fatální chyby může být potřeba obnova přes hosting/FTP.

Ověřeno místně: skutečný WordPress upgrader aktualizoval testovací plugin a šablonu, zachoval aktivaci a ukončil maintenance. Testy zahrnují oprávnění, nonce, zastavení, schválené verze, výběr vydání, limity a hash. Produkční aktualizace touto verzí zatím ověřena není.
