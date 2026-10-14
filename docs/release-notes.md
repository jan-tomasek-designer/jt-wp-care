# JT WP Care 0.12.0-alpha

V diagnostice přibyla první řízená oprava konfigurace. Po výslovném potvrzení použitelné zálohy a zásahu umí JT WP Care nastavit `WP_DEBUG`, `WP_DEBUG_DISPLAY` a `WP_DEBUG_LOG` na `false` v běžném zapisovatelném `wp-config.php`. Serverové `PHP display_errors` zůstává samostatným nastavením hostingu.

Před změnou vznikne datovaná chráněná záloha. Oprava odmítne symbolický odkaz, neplatnou PHP syntaxi, dynamické nebo vícenásobné definice a aktivní hodnotu, jejíž zdroj není ve `wp-config.php` jednoznačně dohledatelný. Zápis probíhá pod zámkem, kontroluje souběžnou změnu a ověří výsledný obsah.

Po zásahu proběhne technický test administrace a základních stránek. Zjištěný problém vyvolá automatický návrat původní konfigurace; neověřitelný HTTP test zůstane viditelným omezením pro ruční kontrolu. Historie zásahu, výsledek testu a název zálohy jsou součástí servisního reportu bez zveřejnění obsahu nebo absolutní cesty.

Diagnostika také slučuje duplicitní nabídky stejné verze WordPressu, které se objevily při produkčním pilotu. Datové schéma servisního záznamu, protokol MU loaderu a instalátor 0.4.0-alpha zůstávají beze změny.
