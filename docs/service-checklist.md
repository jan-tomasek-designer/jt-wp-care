# JT WP Care — servisní checklist

Šablona metodiky 0.1. Pro každý servis vytvořit kopii. Výchozí stav je neověřeno. Zaškrtnout až po splnění podmínek metodiky.

Web / prostředí:
Technik / ID servisu:
Datum, čas a časové pásmo:
Oprávněný rozsah práce / servisní okno:
Kritické funkce / dohodnutý testovací scénář:

## Před změnami

- [ ] Záloha existuje a je doložená.
- [ ] Výchozí diagnostika je zaznamenaná.
- [ ] Plán aktualizací a kompatibilita jsou posouzené.

Záloha: zdroj, čas, rozsah, dokončení, přístup k obnově:
Test obnovy: proveden/neproveden, datum a výsledek:
Známé závady před servisem:

## Základní přehled

- [ ] Aktualizace WP — aktuální stav nebo dokončená ruční aktualizace ověřeny.
- [ ] PHP podporované — verze, zdroj a datum ověření podpory.
- [ ] Aktualizace pluginů — ostatní pluginy vyhodnoceny, změny ověřeny.
- [ ] Aktualizace šablony — parent/child vyhodnoceny, výsledek ověřen.

Přehled zobrazuje stav; provádění se řídí pořadím metodiky. PHP se posuzuje už při vstupní diagnostice.

## WooCommerce

- [ ] WooCommerce — stav ověřen, případná aktualizace poslední v aktualizační části.
- [ ] DB update — nebyl potřeba nebo dokončení ověřeno.
- [ ] GoPay — platební scénář včetně potvrzení a stavu objednávky ověřen.
- [ ] SMTP test — přijetí zprávy potvrzeno.
- [ ] Order test — průchod, stav a transakční e-mail ověřeny.

Bez WooCommerce/GoPay označit příslušné body „netýká se“. SMTP se kontroluje také na běžném webu, který odesílá poštu.
Testovací režim / bezpečný identifikátor objednávky a transakce:
Vypořádání testovacích dat a platby:

## Funkční test

- [ ] Hlavní stránky a administrace.
- [ ] Relevantní formuláře a dohodnuté kritické funkce.

## Security

- [ ] Wordfence — stav ochrany a výsledek kontroly vyhodnoceny.
- [ ] 2FA — pokrytí administrátorských účtů ověřeno.
- [ ] XML-RPC — potřebnost a odpovídající nastavení ověřeny.
- [ ] DISALLOW_FILE_EDIT — ověřena hodnota true.

## Evidence

Pro každý bod přidat řádek. Povolené stavy: neověřeno / splněno / problém / odloženo / netýká se. Nezaškrtnutý bod vždy vysvětlit.

| Bod | Stav | Zjištění / verze před → po | Důkaz a čas | Další krok / odpovědnost |
| --- | --- | --- | --- | --- |
| Doplnit | Neověřeno | | | |

## Report

- [ ] TXT — výsledky, výhrady a doporučení zapsány.
- [ ] PDF — shodné údaje jako TXT, vzhled zkontrolován.

Výsledek: dokončeno / dokončeno s výhradami / přerušeno
Otevřené problémy a další postup:
Ukončení servisu:
Předání reportu, pokud bylo dohodnuto:
