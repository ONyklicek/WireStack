<?php

declare(strict_types=1);

return [
    'breadcrumbs' => 'Drobečková navigace',
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
    'notifications_all' => 'Vše',
    'notifications_unread' => 'Nepřečtené',
    'no_unread_notifications' => 'Nic nepřečteného.',
    'view_all_notifications' => 'Zobrazit vše',
    'mark_unread' => 'Označit jako nepřečtené',
    'delete_notification' => 'Smazat',
    'clear_read' => 'Uklidit přečtené',
    'notifications_today' => 'Dnes',
    'notifications_yesterday' => 'Včera',
    'notifications_earlier' => 'Dříve',
    'notifications_all_read' => 'Vše přečteno',

    // Frontované akce ({@see Actions\Concerns\Queueable}).
    'action_queued' => ':action běží na pozadí.',
    'action_queued_done' => ':action doběhla.',

    // Tři stavy Foundation\Enums\Theme. `System` je taky volba — je to to, kam
    // se musí dát vrátit, aby notebook, který si večer sám ztmaví, dál platil.
    'theme_light' => 'Světlý',
    'theme_system' => 'Systém',
    'theme_dark' => 'Tmavý',

    // Čím soubor je — rodina, ne formát ({@see Foundation\Enums\FileKind}).
    // Ukáže se tam, kde soubor nemá náhled a jeho název nemá příponu, kterou by
    // šlo vypsat místo toho.
    'file_kinds' => [
        'image' => 'Obrázek',
        'video' => 'Video',
        'audio' => 'Zvuk',
        'document' => 'Dokument',
        'spreadsheet' => 'Tabulka',
        'presentation' => 'Prezentace',
        'archive' => 'Archiv',
        'code' => 'Kód',
        'other' => 'Soubor',
    ],
];
