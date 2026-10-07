# Vydání 0.3.0-alpha

Základ pro budoucí řízené aktualizace: stabilní MU loader vybírá jednu kompletní verzi ze složky releases. Instalační ZIP obsahuje loader a počáteční ukazatel; payload ZIP obsahuje pouze konkrétní vydání.

- Kontrola platformy, protokolu a datového schématu před načtením.
- Návrat na předchozí kompatibilní vydání při chybějícím vstupním souboru nebo neplatných metadatech.
- Nouzová volba verze přes JT_WP_CARE_PINNED_RELEASE.
- Manifest a SHA-256 pro kontrolu balíčků.

Alpha pro staging. Manifest zatím není kryptograficky podepsaný. Stahování, instalace z administrace, atomické přepnutí ani automatická obnova po fatální chybě nejsou implementovány. Nedochází k aktualizaci WordPressu, pluginů nebo databázového schématu.

Přechod z 0.2.0 vyžaduje jednorázové ruční nasazení podle README. Samotné rozložení 0.2.0 nelze použít jako předchozí vydání tohoto loaderu.
