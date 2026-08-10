<?php

declare(strict_types=1);

use NyonCode\WireForms\Components\TiptapEditor;
use NyonCode\WireForms\Forms\Form;

/**
 * A starting document for the editor is the canonical `->default()`, not a
 * second editor-only API: the form runtime already seeds it into the state bag,
 * and the field additionally hands it to the Alpine config so a host that never
 * seeded (a null column, a hand-bound property) still opens on the template.
 *
 * The default is markup, so the text arrives pre-formatted.
 */
it('seeds the form state with the default document', function () {
    $form = Form::make()->schema([
        TiptapEditor::make('content')->default('<p>Nějaký <strong>text</strong></p>'),
    ]);

    expect($form->getInitialState())->toBe(['content' => '<p>Nějaký <strong>text</strong></p>']);
});

it('hands the default to the editor so a host that never seeded still shows it', function () {
    $config = TiptapEditor::make('content')
        ->default('<h2>Brief</h2><ul><li>Goal</li></ul>')
        ->getAlpineConfig();

    expect($config['default'])->toBe('<h2>Brief</h2><ul><li>Goal</li></ul>');
});

it('evaluates a closure default like every other configuration callback', function () {
    $field = TiptapEditor::make('content')->default(fn (): string => '<p>Generated</p>');

    expect($field->getDefaultContent())->toBe('<p>Generated</p>');
});

it('carries no default content when none was configured', function () {
    expect(TiptapEditor::make('content')->getDefaultContent())->toBe('')
        ->and(TiptapEditor::make('content')->getAlpineConfig()['default'])->toBe('');
});

it('treats a non-string default as no editor content', function () {
    // ->default() is `mixed` on every component; only a document string is
    // content the editor can open on, so anything else must not reach the JS.
    expect(TiptapEditor::make('content')->default(42)->getDefaultContent())->toBe('')
        ->and(TiptapEditor::make('content')->default(null)->getDefaultContent())->toBe('');
});

it('accepts a JSON document string as the default under outputJson()', function () {
    $doc = json_encode([
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => 'Nějaký text']],
        ]],
    ], JSON_THROW_ON_ERROR);

    $field = TiptapEditor::make('content')->outputJson()->default($doc);

    expect($field->getAlpineConfig())
        ->toMatchArray(['outputFormat' => 'json', 'default' => $doc]);
});
