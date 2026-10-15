# WooCommerce audit (0.6.0-alpha)

Samostatný modul `woocommerce` přidává výsledky do existující diagnostiky a reportu. Lze jej vypnout přes `JT_WP_CARE_DISABLED_MODULES`. Používá filtr `jt_wp_care_automatic_results`; nezavádí další ukládání ani změnu datového schématu. Ruční záznam DB update zůstává zachován: kontrola požadavku není potvrzením integrity databáze.

- `WC_Install::needs_db_update()` se čte pouze při existující platné verzi databáze. Chybějící API/metadata znamenají Neověřeno. Rozdílná verze pluginu a databáze sama není chyba.
- Action Scheduler se dotazuje až po `action_scheduler_init`. Pět dotazů vrací nejvýše jedno ID: neúspěšná úloha celého webu, čekající úloha opožděná více než hodinu a pending/in-progress/failed ve skupině `woocommerce-db-updates`.
- Neúspěšné úlohy mohou být historické. Fronta celého webu zahrnuje další pluginy, nejde výhradně o WooCommerce. Prázdná fronta neprokazuje funkční cron. Jiné mechanismy migrace ani HPOS synchronizace tím nejsou ověřené.
- Výjimka nebo neplatný výsledek ponechá stav Neověřeno. Žádné spuštění, opakování, rušení úloh, zápisy do WooCommerce ani načítání argumentů úloh či zákaznických dat.
- Platí hodinové obnovení a expirace snímku z automatického modulu. Po aktualizaci lze použít existující ruční obnovení diagnostiky pro okamžitý výsledek.

Rozhraní ověřena podle [WooCommerce WC_Install](https://woocommerce.github.io/code-reference/classes/WC-Install.html) a [Action Scheduler API](https://actionscheduler.org/api/). Integrace je chráněna kontrolou dostupnosti API pro zachování kompatibility.

Ověření: `php tests/woocommerce.php` kontroluje nedostupné WooCommerce/API, chybějící metadata, požadavek DB, inicializaci fronty, limit dotazů, hodinovou hranici, migrace, chyby a vypnutí modulu. Produkční ověření nové verze na MRA ještě neproběhlo.
