# JT WP Care 0.13.0-alpha

Checklist nyní automaticky uzavírá položky, které lze doložit přímo z WordPressu: výchozí technickou diagnostiku, podporu PHP, stav aktualizací WordPressu, ostatních pluginů, šablon a WooCommerce, požadavek na databázovou migraci WooCommerce, technickou dostupnost webu a administrace a hodnotu `DISALLOW_FILE_EDIT`.

Známé nepodporované větve PHP 8.0 a 8.1 se zobrazí jako problém podle oficiálního přehledu PHP. PHP 8.1.13 zjištěné na livemotion.cz proto vyžaduje naplánovanou změnu hostingu nebo verze PHP; samotný plugin PHP nemění.

Aktualizační stav používá pouze cache aktualizačního API WordPressu mladší než jednu hodinu. Nalezená nabídka znamená problém, čerstvá cache bez nabídky znamená splněný technický stav a starší nebo chybějící cache zůstává neověřená. Ruční načtení nabídek vyžádá nový diagnostický snímek. Chybějící nebo zastaralé důkazy se obnoví ověřeným POST požadavkem také při otevření checklistu.

Technická kontrola webu potvrzuje přihlášenou relaci správce, načtení administrace a omezené HTTP/HTML odpovědi úvodní a nalezených základních stránek. Nehodnotí vzhled, JavaScript ani neodesílá formuláře. Tyto činnosti mají samostatný ruční bod „Vizuální a kritické funkce“.

Záloha a možnost obnovy, schválený plán zásahů, GoPay, skutečné doručení SMTP zprávy, objednávka, Wordfence scan, 2FA a závislosti XML-RPC zůstávají ručními kroky. Vydání nespouští aktualizace ani jiné změny bez potvrzení správce.
