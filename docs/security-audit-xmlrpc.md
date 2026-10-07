# Security Audit — XML-RPC

Pracovní specifikace z předání zkušenosti dne 2026-10-06. Dosud neimplementováno; předaný text nepotvrzuje provedené vypnutí ani úspěšné testy konkrétního webu.

## Výstup kontroly

Evidovat odděleně stav XML-RPC (aktivní / blokované v ověřeném rozsahu / neověřeno), způsob a rozsah ověření, čas a nalezené potenciální závislosti. Lokální nastavení nesmí být prezentováno jako důkaz dostupnosti z internetu. Technický způsob detekce bude před implementací ověřen proti dokumentaci WordPressu a integrací.

Závislosti hledat mezi aktivními a síťově aktivními pluginy podle udržovaného seznamu. Nález Jetpacku znamená potřebu prověření využívaných funkcí. Zvlášť požadovat vyjádření správce ke vzdálenému publikování, mobilním klientům a vlastním integracím, které nemusí být viditelné v seznamu pluginů.

## Doporučení

| Zjištění | Výsledek |
| --- | --- |
| Známá nebo potvrzená závislost | Nedoporučovat plošné vypnutí; prověřit požadavky integrace. |
| Žádná známá závislost, externí použití neověřeno | Vyžádat potvrzení správce; nepovažovat vypnutí za bezpečně ověřené. |
| Žádná známá závislost a správce potvrdil nepotřebnost | Doporučit ruční vypnutí s plánem návratu a následným testem. |
| Stav nebo rozsah kontroly nejasný | Neověřeno, uvést omezení. |
| Blokování ověřené | Zapsat rozsah důkazu a ověřit potřebné funkce; neopakovat zásah. |

V1.0 pouze zjišťuje, vysvětluje a eviduje. Nemění nastavení XML-RPC, konfiguraci serveru ani bezpečnostního pluginu.

## Následné ověření

Po ruční změně: stav XML-RPC, přihlášení, administrace, relevantní WooCommerce průchod a doručení e-mailů; navíc známé externí integrace. Každý test má výsledek, čas a důkaz. Na webu bez WooCommerce je tento dílčí test „netýká se“. Selhání vede k řešení incidentu a případnému návratu změny, ne k zaškrtnutí bodu.

## Akceptační scénáře pro budoucí modul

- Aktivní nebo síťově aktivní známá integrace zabrání obecnému doporučení vypnout.
- Nenalezené pluginové závislosti bez potvrzení správce nevedou k tvrzení „bezpečné vypnutí“.
- Neúplná nebo neúspěšná detekce vrací neověřeno.
- Výsledky před a po změně zůstanou rozlišené, včetně metody ověření.
- Žádná auditní cesta nemění konfiguraci.
- Splnění servisního kroku vyžaduje doložené relevantní funkční testy.
