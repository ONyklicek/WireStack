<?php

declare(strict_types=1);

return [
    'create' => 'Nový/á :label',
    'edit' => 'Upravit :label',
    'save' => 'Uložit',

    /*
     * What a record's tabs are called when the page declared no label of
     * its own. Only the kinds that take a record are ever drawn as tabs,
     * so only those are named here; anything else is the application's own
     * key, humanised, or whatever `RoutePage::label()` said.
     */
    'page_kind' => [
        'view' => 'Detail',
        'edit' => 'Upravit',
    ],

    'record_pages' => 'Stránky záznamu',
];
