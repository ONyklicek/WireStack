<?php

declare(strict_types=1);

return [
    'yes' => 'Yes',
    'no' => 'No',
    'search' => 'Search...',
    'select_all' => 'Select all',
    'deselect_all' => 'Deselect all',
    'no_results' => 'No results found',
    'loading' => 'Loading...',
    'create_option' => 'Create option',
    'edit_option' => 'Edit option',
    'create' => 'Create',
    'save' => 'Save',
    'cancel' => 'Cancel',

    // Shared editor toolbar vocabulary: TiptapEditor, RichEditor and
    // MarkdownEditor all title their buttons from these keys, so the three
    // editors read alike in every locale and a reworded button is reworded once.
    'editor' => [
        'bold' => 'Bold',
        'italic' => 'Italic',
        'underline' => 'Underline',
        'strike' => 'Strikethrough',
        'code' => 'Inline code',
        'highlight' => 'Highlight',
        'heading' => 'Heading :level',
        'bullet_list' => 'Bullet list',
        'ordered_list' => 'Numbered list',
        'blockquote' => 'Blockquote',
        'code_block' => 'Code block',
        'link' => 'Link',
        'image' => 'Image',
        'table' => 'Table',
        'align_left' => 'Align left',
        'align_center' => 'Align center',
        'align_right' => 'Align right',
        'undo' => 'Undo',
        'redo' => 'Redo',

        // MarkdownEditor's write/preview tabs.
        'write' => 'Write',
        'preview' => 'Preview',

        // Browser prompt() titles — resolved in PHP and handed to the editor JS
        // through the Alpine config, so they follow the app locale too.
        'link_url' => 'Link URL',
        'image_url' => 'Image URL',
    ],
];
