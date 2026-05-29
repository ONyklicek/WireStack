-- plugin systém

Důležitá nuance: runtime hook dispatch teď pořád používá runHook()
s array payloadem kvůli zpětné kompatibilitě.
Typed payloady jsou připravené a otestované, ale nejsou vynucené jako jediný způsob.

Co to zatím není: marketplace systém, auto-discovery podle composer package manifestů,
hot enable/disable pluginů, unhook mechanismus,
izolované sandboxování pluginů nebo automatický factory builder pro string typy.
Na cross-cutting chování, volitelné balíky,
query/form/action lifecycle a registraci typů je to postavené dobře.

-- sortable
Problém při změně pořadí sloupců (pořadí se vrátí zpět do původní polohy)