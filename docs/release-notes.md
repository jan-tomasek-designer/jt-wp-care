# JT WP Care 0.8.0-alpha

Checklist a panel automatických kontrol nyní barevně rozlišují splněno, problém, odloženo, neověřeno a netýká se. Barva ručního řádku se mění už při výběru stavu, před uložením.

Nový vypínatelný modul `functional-tests` potvrzuje aktuální přihlášenou relaci správce a úspěšné načtení administračního požadavku. Přes omezené read-only GET požadavky kontroluje HTML dostupnost úvodní stránky a nejvýše pěti základních publikovaných stránek stejného webu, včetně nalezených stránek WooCommerce. Neodesílá formuláře, nevytváří objednávky a nehodnotí vzhled, JavaScript ani správnost obsahu. Transportní chyba je Neověřeno, HTTP 4xx/5xx je problém.

Ruční bod „Přihlášení, web a administrace“ zůstává, protože automatická dostupnost nenahrazuje reálný test v prohlížeči. Výsledek je součástí diagnostiky a reportu. Modul lze vypnout přes `JT_WP_CARE_DISABLED_MODULES`.

Řízené aktualizace pluginů a šablon: načíst nabídky, vybrat verze, potvrdit zálohu a citlivé změny, spustit seznam. Plugin aktualizuje postupně a při chybě zastaví. WooCommerce poslední; aktivní child theme se tímto postupem nepřepisuje. Historie výsledků je v reportu. Funkční test a dostupná obnova zůstávají nutné.

Instalátor 0.2.0-alpha umí stáhnout publikované vydání JT WP Care z veřejného GitHubu a sám ověřit SHA-256. Není potřeba ručně nahrávat payload ani zadávat hash. Starý instalátor je nutné jednou nahradit ZIPem běžného pluginu. Pro alpha vydání zaškrtnout testovací kanál. Repozitář i release musí být veřejně dostupné; tento text sám nepotvrzuje jejich publikaci.

Loader a datové schéma zůstávají 1. Historie aktualizací používá samostatné options a zachovává checklist. Nové operace jsou jen po potvrzení, nikoli na pozadí. Platí limity direct filesystem a disk_free_space pro instalátor JT WP Care. V případě fatální chyby může být potřeba obnova přes hosting/FTP.

Ověřeno místně: skutečný WordPress upgrader aktualizoval testovací plugin a šablonu, zachoval aktivaci a ukončil maintenance. Testy zahrnují oprávnění, nonce, zastavení, schválené verze, výběr vydání, limity a hash. Produkční aktualizace touto verzí zatím ověřena není.
