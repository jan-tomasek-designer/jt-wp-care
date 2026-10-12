# Vydání 0.5.0-alpha

Diagnostika se při otevření JT WP Care obnovuje automaticky nejvýše jednou za hodinu. Nový vypínatelný modul vyhodnotí podporu větve PHP, editor souborů, debug režim a uložené nabídky aktualizací. PHP a DISALLOW_FILE_EDIT již nevyžadují ruční zaškrtávání; původní poznámky a ruční záznamy zůstávají zachované. Export zahrnuje automatické výsledky.

Automatika neprovádí aktualizace webu, neposílá poštu a nepotvrzuje zálohy ani funkční testy. Aktualizační cache se nestahuje; chybějící nabídka není důkaz aktuálnosti. Pravidla PHP ověřena 2026-10-12, platná do 2027-04-12. Podrobnosti: docs/automatic-checks.md.

Datové schéma a loader zůstávají 1. Pomocný instalátor je 0.1.2-alpha a má vlastní omezení hostingu; tato verze neobchází nedostupné disk_free_space. Pro aktualizaci používej payload 0.5.0 a jeho nový SHA-256, ne starý součet z 0.4.
