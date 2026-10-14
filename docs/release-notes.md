# JT WP Care 0.9.0-alpha

Řízené aktualizace nyní po každé úspěšně nainstalované položce automaticky spustí omezený technický post-test. Ověří aktuální přihlášenou relaci a administrační požadavek a pomocí read-only GET požadavků zkontroluje úvodní a nejvýše čtyři základní stránky stejného webu.

Pokud kontrola najde HTTP problém nebo ji nelze dokončit, další položky schváleného seznamu se nespustí. Historie uloží nainstalovanou verzi, čas a výsledek post-testu. Přerušený krok se automaticky neopakuje.

Méně citlivé nabídky jsou v seznamu předvybrané jako doporučené. Aktivní citlivé pluginy, šablony a WooCommerce zůstávají bez předvýběru a vyžadují vlastní potvrzení. WooCommerce se řadí nakonec a aktivní child theme zůstává blokovaná.

Technický post-test neodesílá formuláře, e-maily ani objednávky a nehodnotí vzhled, JavaScript, obsah nebo dokončení databázových migrací. Ruční funkční kontrola a dostupná obnova proto zůstávají součástí metodiky.

Instalátor 0.2.0-alpha stahuje toto testovací vydání z veřejného GitHub Releases, ověří manifest i SHA-256 a zachová checklist a servisní data. Aktualizace se spouští pouze po potvrzení správce a zálohy; neběží bezobslužně.
