# JT WP Care 0.12.1-alpha

Toto opravné vydání umožňuje řízenou opravu konfigurace také na hostinzích, kde je zapisovatelný samotný `wp-config.php`, ale PHP nesmí vytvořit novou složku vedle něj. JT WP Care v takovém případě uloží chráněnou zálohu do trvalé spravované složky `wp-content/mu-plugins/jt-wp-care/config-backups`, která není součástí verzovaného vydání.

Před změnou vznikne datovaná chráněná záloha. Obě povolená umístění používají náhodný název, PHP ochranu přímého přístupu, ochranné soubory pro Apache/IIS a omezená práva. Oprava odmítne symbolický odkaz, neplatnou PHP syntaxi, dynamické nebo vícenásobné definice a aktivní hodnotu, jejíž zdroj není ve `wp-config.php` jednoznačně dohledatelný. Zápis probíhá pod zámkem, kontroluje souběžnou změnu a ověří výsledný obsah.

Po zásahu proběhne technický test administrace a základních stránek. Zjištěný problém vyvolá automatický návrat původní konfigurace; neověřitelný HTTP test zůstane viditelným omezením pro ruční kontrolu. Historie zásahu, výsledek testu a název zálohy jsou součástí servisního reportu bez zveřejnění obsahu nebo absolutní cesty.

Diagnostika také slučuje duplicitní nabídky stejné verze WordPressu, které se objevily při produkčním pilotu. Datové schéma servisního záznamu, protokol MU loaderu a instalátor 0.4.0-alpha zůstávají beze změny.
