# JT WP Care 0.10.0-alpha

Administrace JT WP Care je rozdělená na pět samostatných záložek: Přehled, Checklist, Diagnostika a bezpečnost, Aktualizace a Report. Přehled ukazuje počet splněných, problémových, odložených a neověřených bodů, stav automatické diagnostiky a stav řízeného aktualizačního plánu. Každá dlaždice vede přímo do příslušné sekce.

Aktualizační pravidla z verze 0.9 zůstávají: každá schválená aktualizace se provede v samostatném kroku a technický post-test běží až v novém požadavku WordPressu. Další položka se uvolní pouze po potvrzeném výsledku administrace, úvodní a základních stránek.

Potvrzení instalačního kroku používá skutečně načtenou cílovou verzi a zachovaný stav aktivace. Tím opravuje falešný neúspěch pozorovaný na MRA, kde se Complianz nainstaloval na 7.5.6, ale nejednoznačná návratová hodnota WordPress upgraderu zastavila plán.

Součástí vydání je běžný plugin instalátoru 0.3.0-alpha. Pokud hosting zakazuje PHP funkci `disk_free_space`, instalátor už aktualizaci automaticky nezablokuje. Potřebnou kapacitu ověří skutečným omezeným zápisem do dočasného souboru ve stejné instalační složce, soubor uzavře a odstraní ještě před rozbalením. Při nedokončeném zápisu se aktualizace zastaví bez přepnutí aktivní verze.

Instalátor 0.3.0-alpha je na web s instalátorem 0.2 nutné jednou nahrát přes Pluginy → Instalace pluginů a nahradit původní verzi. Poté další vydání JT WP Care opět načítá z veřejného GitHub Releases bez ručního payloadu a SHA-256.

Datové schéma a loader zůstávají ve verzi 1. Checklist, diagnostika i historie aktualizací se zachovají.
