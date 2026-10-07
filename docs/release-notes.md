# Vydání 0.4.0-alpha

Checklist předvyplňuje z uložené diagnostiky verze, nabídky aktualizací a zjistitelná nastavení. U každého bodu ukazuje konkrétní pokyn pro ruční ověření. Poznámky a stavy správce zůstávají zachované. Nová diagnostika doplňuje cache WordPressu/šablon a omezenou detekci SMTP pluginů.

Samostatný pomocný instalátor umožní přechod z 0.3 přes administraci WordPressu: ruční nahrání payload ZIPu z našeho repozitáře, SHA-256, potvrzení, instalace vedle původní verze a možnost návratu. Podrobný postup: docs/wordpress-update.md. Do běžného instalátoru WordPressu patří pouze jt-wp-care-installer-0.1.0-alpha.zip.

Alpha: nejprve staging a ověřená záloha. Přímé stahování z GitHubu, podpisy manifestů a automatická obnova po fatální chybě nejsou implementovány. Vyžadován přímý zápis, PHP zip a nezměněný podporovaný loader. Datové schéma zůstává 1; WordPress, WooCommerce ani jiné pluginy se neaktualizují.
