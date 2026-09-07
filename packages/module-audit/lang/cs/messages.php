<?php

declare(strict_types=1);

/*
 * Co říká tenhle modul, a nic víc.
 *
 * Slova sdílená s historií změn v jádře — „Systém“, „Neznámý uživatel“,
 * nadpisy pole/původní/nová a „(prázdné)“ — tu schválně **nejsou**: patří do
 * `wire-core::audit`, a dvě obrazovky, které si o tom, kdo je „Systém“,
 * odporují, jsou přesně ta drobná nepravda, kterou si audit log nemůže dovolit.
 *
 * Názvy událostí tu jsou, protože ty v jádře jsou části vět do časové osy
 * („upravil(a) tento záznam“) — správný tvar tam, špatný uvnitř odznaku nebo
 * filtru.
 */

return [
    'system' => 'Systém',
    'entry' => 'Záznam auditu',
    'entries' => 'Audit log',

    'event' => 'Událost',
    'record' => 'Záznam',
    'actor' => 'Kdo',
    'when' => 'Kdy',
    'changed' => 'Změněno',

    'event_created' => 'Vytvoření',
    'event_updated' => 'Úprava',
    'event_deleted' => 'Smazání',
    'event_bulk_action' => 'Hromadná akce',
    'event_cell_updated' => 'Úprava buňky',

    'what_happened' => 'Co se stalo',
    'changes' => 'Změny',
    'no_changes' => 'Žádné pole se nezměnilo.',

    'context' => 'Požadavek',
    'context_key' => 'Údaj',
    'context_value' => 'Hodnota',
    'no_context' => 'Zaznamenáno mimo požadavek.',

    'open_record' => 'Otevřít záznam',

    'empty_heading' => 'Zatím nic zaznamenáno',
    'empty_description' => 'Záznamy se tu objeví, jakmile budou auditované modely vznikat, měnit se a mizet.',
];
