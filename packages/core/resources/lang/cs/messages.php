<?php

declare(strict_types=1);

return [
    // Sdílené hlášky inline editace (editovatelné panel entry).
    'error' => 'Něco se pokazilo.',
    'save_failed' => 'Uložení se nezdařilo. Zkuste to prosím znovu.',
    'save_error' => 'Uložení se nezdařilo: :error',
    'validation_failed' => 'Hodnota není platná.',
    'record_conflict' => 'Záznam byl mezitím změněn jinde. Zobrazena je aktuální hodnota.',
    'record_not_found' => 'Záznam nebyl nalezen.',
    'entry_not_editable' => 'Toto pole nelze upravovat.',
    'no_permission' => 'Nemáte oprávnění upravovat toto pole.',
    'no_permission_edit' => 'Nemáte oprávnění upravovat tento záznam.',

    // The copy-to-clipboard affordance ({@see Foundation\View\CopyButton}).
    'copy' => 'Kopírovat',
    'copied' => 'Zkopírováno!',

    // Zvoneček notifikací ({@see Notifications\NotificationBell}).
    'notifications' => 'Oznámení',
    'no_notifications' => 'Zatím tu nic není.',
    'mark_read' => 'Označit jako přečtené',
    'mark_all_read' => 'Označit vše',

    // Frontované akce ({@see Actions\Concerns\Queueable}).
    'action_queued' => ':action běží na pozadí.',
    'action_queued_done' => ':action doběhla.',
];
