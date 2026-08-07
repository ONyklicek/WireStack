<?php

declare(strict_types=1);

return [
    'yes' => 'Ano',
    'no' => 'Ne',
    'search' => 'Hledat...',
    'select_all' => 'Vybrat vše',
    'deselect_all' => 'Zrušit výběr',
    'no_results' => 'Žádné výsledky',
    'loading' => 'Načítání...',
    'create_option' => 'Vytvořit položku',
    'edit_option' => 'Upravit položku',
    'create' => 'Vytvořit',
    'save' => 'Uložit',
    'cancel' => 'Zrušit',

    // Sdílená slovní zásoba nástrojové lišty editorů: TiptapEditor, RichEditor
    // i MarkdownEditor popisují tlačítka z těchto klíčů, takže všechny tři
    // editory zní stejně v každém jazyce a přeformulace se dělá jen jednou.
    'editor' => [
        'bold' => 'Tučné',
        'italic' => 'Kurzíva',
        'underline' => 'Podtržené',
        'strike' => 'Přeškrtnuté',
        'code' => 'Kód v textu',
        'highlight' => 'Zvýraznění',
        'heading' => 'Nadpis :level',
        'bullet_list' => 'Odrážkový seznam',
        'ordered_list' => 'Číslovaný seznam',
        'blockquote' => 'Citace',
        'code_block' => 'Blok kódu',
        'link' => 'Odkaz',
        'image' => 'Obrázek',
        'table' => 'Tabulka',
        'align_left' => 'Zarovnat vlevo',
        'align_center' => 'Zarovnat na střed',
        'align_right' => 'Zarovnat vpravo',
        'undo' => 'Zpět',
        'redo' => 'Znovu',

        // Záložky psaní/náhledu v MarkdownEditoru.
        'write' => 'Psát',
        'preview' => 'Náhled',

        // Titulky prohlížečových prompt() dialogů — překládají se v PHP a do JS
        // se předávají v Alpine konfiguraci, takže respektují jazyk aplikace.
        'link_url' => 'URL odkazu',
        'image_url' => 'URL obrázku',
    ],
];
