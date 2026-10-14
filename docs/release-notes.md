# JT WP Care 0.11.0-alpha

Nástěnka nyní ukazuje barevný stav celého servisu i jednotlivých oblastí: hotovo, čeká a problém. Souhrn odděleně počítá dokončené, neověřené, odložené a problémové body, takže není nutné procházet celý checklist.

V záložce Aktualizace přibyl řízený úklid neaktivních pluginů. Správce vždy vybere jeden konkrétní plugin a samostatně potvrdí, že jej web nepotřebuje a že je ověřená záloha. Aktivní pluginy se nenabízejí. WooCommerce, instalátor JT WP Care a pluginy rozpoznané jako závislost aktivního pluginu jsou chráněné. Po odstranění proběhne technický post-test a výsledek se uloží.

Kontrola stránek nově rozpozná známé texty fatální PHP chyby, chyby databázového připojení a režimu údržby také tehdy, když server chybně vrátí HTTP 200. Test zůstává omezený na čtení a neodesílá formuláře, objednávky ani e-maily.

Součástí vydání je instalátor 0.4.0-alpha. Před stažením z GitHubu uloží cílovou verzi a po obnovení stránky ji porovná se skutečně aktivním ukazatelem. Pokud pomalý hosting nechá prohlížeč viset u rozbalování, správce instalaci neopakuje: obnoví stránku instalátoru a dostane jednoznačný stav. Na hostingu bez `disk_free_space` zůstává náhradní kontrola kapacity z verze 0.3.

Datové schéma servisního záznamu i protokol MU loaderu zůstávají beze změny.
