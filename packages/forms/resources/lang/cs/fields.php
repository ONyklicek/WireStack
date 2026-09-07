<?php

declare(strict_types=1);

return [
    'yes' => 'Ano',
    'no' => 'Ne',
    'search' => 'Hledat...',
    'select_all' => 'Vybrat vše',
    'deselect_all' => 'Zrušit výběr',
    'deselect' => 'Odebrat',
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

    // Vlastní validační hlášky MoneyInputu: částka se skrývá za oddělovači
    // skupin, takže se meze hlásí ve stejném formátu, v jakém je uživatel
    // napsal, ne jako holé číslo.
    'money' => [
        'invalid' => 'Pole :attribute musí být částka.',
        'min' => 'Pole :attribute musí být alespoň :min.',
        'max' => 'Pole :attribute nesmí být větší než :max.',
    ],

    // Validační hlášky PhoneInputu. Číslo se kontroluje ve třech krocích — je
    // vůbec mezinárodní, je jeho předvolba mezi nabízenými, má národní část
    // tolik číslic, kolik daná země vydává — a každý má vlastní hlášku.
    'phone' => [
        'invalid' => 'Pole :attribute musí být platné mezinárodní telefonní číslo.',
        'country' => 'Pole :attribute musí být číslo z některé z nabízených zemí.',
        'length' => 'Pole :attribute musí mít za předvolbou :min až :max číslic.',
    ],

    // Popisky přímo na plátně SignaturePadu.
    'signature' => [
        'hint' => 'Podepište se zde',
        'clear' => 'Vymazat',
    ],

    // DateRangePicker: oba konce období a nabídka období na jedno kliknutí.
    'range' => [
        'from' => 'Od',
        'to' => 'Do',
        'today' => 'Dnes',
        'this_week' => 'Tento týden',
        'this_month' => 'Tento měsíc',
        'last_30_days' => 'Posledních 30 dní',
        'this_year' => 'Tento rok',
    ],
];
