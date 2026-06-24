<?php

declare(strict_types=1);

use NyonCode\WireForms\Components\CodeEditor;
use NyonCode\WireForms\Components\KeyValue;
use NyonCode\WireForms\Components\MarkdownEditor;
use NyonCode\WireForms\Components\OtpInput;
use NyonCode\WireForms\Components\Rating;
use NyonCode\WireForms\Components\Slider;
use NyonCode\WireForms\Components\Tags;
use NyonCode\WireForms\Components\TiptapEditor;

// ─── Rating ────────────────────────────────────────────────────

test('rating defaults', function () {
    $field = Rating::make('score');

    expect($field->getMax())->toBe(5)
        ->and($field->isAllowHalf())->toBeFalse()
        ->and($field->getColor())->toBe('warning')
        ->and($field->isClearable())->toBeTrue()
        ->and($field->getStateType())->toBe('int')
        ->and($field->render()->name())->toBe('wire-forms::components.rating');
});

test('rating fluent configuration', function () {
    $field = Rating::make('score')
        ->max(10)
        ->allowHalf()
        ->color('primary')
        ->clearable(false);

    expect($field->getMax())->toBe(10)
        ->and($field->isAllowHalf())->toBeTrue()
        ->and($field->getColor())->toBe('primary')
        ->and($field->isClearable())->toBeFalse()
        ->and($field->getStateType())->toBe('float');
});

// ─── Slider ────────────────────────────────────────────────────

test('slider defaults', function () {
    $field = Slider::make('volume');

    expect($field->getMin())->toBe(0)
        ->and($field->getMax())->toBe(100)
        ->and($field->getStep())->toBe(1)
        ->and($field->isShowValue())->toBeTrue()
        ->and($field->getColor())->toBe('var(--color-primary-600, #2563eb)')
        ->and($field->getStateType())->toBe('int')
        ->and($field->render()->name())->toBe('wire-forms::components.slider');
});

test('slider fluent configuration with float step', function () {
    $field = Slider::make('ratio')
        ->min(0.5)
        ->max(9.5)
        ->step(0.5)
        ->showValue(false)
        ->color('#f59e0b');

    expect($field->getMin())->toBe(0.5)
        ->and($field->getMax())->toBe(9.5)
        ->and($field->getStep())->toBe(0.5)
        ->and($field->isShowValue())->toBeFalse()
        ->and($field->getColor())->toBe('#f59e0b')
        ->and($field->getStateType())->toBe('float');
});

test('slider max accepts a closure', function () {
    $field = Slider::make('limit')->max(fn () => 250);

    expect($field->getMax())->toBe(250);
});

// ─── OtpInput ──────────────────────────────────────────────────

test('otp input defaults', function () {
    $field = OtpInput::make('code');

    expect($field->getLength())->toBe(6)
        ->and($field->isNumericOnly())->toBeFalse()
        ->and($field->isMasked())->toBeFalse()
        ->and($field->getSeparator())->toBeNull()
        ->and($field->render()->name())->toBe('wire-forms::components.otp-input');
});

test('otp input fluent configuration', function () {
    $field = OtpInput::make('pin')
        ->length(4)
        ->numericOnly()
        ->masked()
        ->separator(2);

    expect($field->getLength())->toBe(4)
        ->and($field->isNumericOnly())->toBeTrue()
        ->and($field->isMasked())->toBeTrue()
        ->and($field->getSeparator())->toBe(2);
});

// ─── Tags ──────────────────────────────────────────────────────

test('tags defaults', function () {
    $field = Tags::make('labels');

    expect($field->getSuggestions())->toBe([])
        ->and($field->getSplitKeys())->toBe(['Enter', ','])
        ->and($field->getMinItems())->toBeNull()
        ->and($field->getMaxItems())->toBeNull()
        ->and($field->isAllowNew())->toBeTrue()
        ->and($field->isAllowDuplicates())->toBeFalse()
        ->and($field->getRelationship())->toBeNull()
        ->and($field->getTitleAttribute())->toBeNull()
        ->and($field->getStateType())->toBe('array')
        ->and($field->render()->name())->toBe('wire-forms::components.tags');
});

test('tags fluent configuration', function () {
    $field = Tags::make('labels')
        ->suggestions(['php', 'js'])
        ->splitKeys(['Tab'])
        ->minItems(1)
        ->maxItems(5)
        ->allowNew(false)
        ->allowDuplicates()
        ->relationship('tags', 'name');

    expect($field->getSuggestions())->toBe(['php', 'js'])
        ->and($field->getSplitKeys())->toBe(['Tab'])
        ->and($field->getMinItems())->toBe(1)
        ->and($field->getMaxItems())->toBe(5)
        ->and($field->isAllowNew())->toBeFalse()
        ->and($field->isAllowDuplicates())->toBeTrue()
        ->and($field->getRelationship())->toBe('tags')
        ->and($field->getTitleAttribute())->toBe('name');
});

test('tags suggestions accept a closure', function () {
    $field = Tags::make('labels')->suggestions(fn () => ['a', 'b']);

    expect($field->getSuggestions())->toBe(['a', 'b']);
});

// ─── KeyValue ──────────────────────────────────────────────────

test('key value defaults', function () {
    $field = KeyValue::make('meta');

    expect($field->getKeyLabel())->toBe('Key')
        ->and($field->getValueLabel())->toBe('Value')
        ->and($field->getKeyPlaceholder())->toBeNull()
        ->and($field->getValuePlaceholder())->toBeNull()
        ->and($field->isAddable())->toBeTrue()
        ->and($field->isDeletable())->toBeTrue()
        ->and($field->isReorderable())->toBeFalse()
        ->and($field->isKeyEditable())->toBeTrue()
        ->and($field->getStateType())->toBe('array')
        ->and($field->render()->name())->toBe('wire-forms::components.key-value');
});

test('key value fluent configuration', function () {
    $field = KeyValue::make('meta')
        ->keyLabel('Attribute')
        ->valueLabel('Setting')
        ->keyPlaceholder('name')
        ->valuePlaceholder('value')
        ->reorderable()
        ->keyEditable(false);

    expect($field->getKeyLabel())->toBe('Attribute')
        ->and($field->getValueLabel())->toBe('Setting')
        ->and($field->getKeyPlaceholder())->toBe('name')
        ->and($field->getValuePlaceholder())->toBe('value')
        ->and($field->isReorderable())->toBeTrue()
        ->and($field->isKeyEditable())->toBeFalse();
});

test('key value action flags are disabled when the field is disabled', function () {
    $field = KeyValue::make('meta')->reorderable()->disabled();

    expect($field->isAddable())->toBeFalse()
        ->and($field->isDeletable())->toBeFalse()
        ->and($field->isReorderable())->toBeFalse()
        ->and($field->isKeyEditable())->toBeFalse();
});

test('key value labels accept closures', function () {
    $field = KeyValue::make('meta')
        ->keyLabel(fn () => 'K')
        ->valueLabel(fn () => 'V');

    expect($field->getKeyLabel())->toBe('K')
        ->and($field->getValueLabel())->toBe('V');
});

// ─── CodeEditor ────────────────────────────────────────────────

test('code editor defaults', function () {
    $field = CodeEditor::make('snippet');

    expect($field->getLanguage())->toBe('plaintext')
        ->and($field->getMinHeight())->toBe(200)
        ->and($field->hasLineNumbers())->toBeTrue()
        ->and($field->getMaxLength())->toBeNull()
        ->and($field->render()->name())->toBe('wire-forms::components.code-editor');
});

test('code editor fluent configuration', function () {
    $field = CodeEditor::make('snippet')
        ->language('php')
        ->minHeight(400)
        ->withLineNumbers(false)
        ->maxLength(1000);

    expect($field->getLanguage())->toBe('php')
        ->and($field->getMinHeight())->toBe(400)
        ->and($field->hasLineNumbers())->toBeFalse()
        ->and($field->getMaxLength())->toBe(1000);
});

// ─── MarkdownEditor ────────────────────────────────────────────

test('markdown editor defaults', function () {
    $field = MarkdownEditor::make('body');

    expect($field->hasPreview())->toBeTrue()
        ->and($field->isLivePreview())->toBeFalse()
        ->and($field->getMinHeight())->toBe(200)
        ->and($field->getMaxLength())->toBeNull()
        ->and($field->render()->name())->toBe('wire-forms::components.markdown-editor');
});

test('markdown editor without preview', function () {
    $field = MarkdownEditor::make('body')->withPreview(false);

    expect($field->hasPreview())->toBeFalse();
});

test('markdown editor live preview implies preview', function () {
    $field = MarkdownEditor::make('body')
        ->withPreview(false)
        ->livePreview()
        ->minHeight(300)
        ->maxLength(500);

    expect($field->isLivePreview())->toBeTrue()
        ->and($field->hasPreview())->toBeTrue()
        ->and($field->getMinHeight())->toBe(300)
        ->and($field->getMaxLength())->toBe(500);
});

// ─── TiptapEditor ──────────────────────────────────────────────

test('tiptap editor defaults', function () {
    $field = TiptapEditor::make('content');

    expect($field->getToolbarButtons())->toBe(TiptapEditor::DEFAULT_TOOLBAR)
        ->and($field->getOutputFormat())->toBe('html')
        ->and($field->getMinHeight())->toBe(240)
        ->and($field->getMaxLength())->toBeNull()
        ->and($field->isWithImages())->toBeFalse()
        ->and($field->isWithTables())->toBeFalse()
        ->and($field->isWithTextAlign())->toBeFalse()
        ->and($field->isWithHighlight())->toBeFalse()
        ->and($field->getFileAttachmentsDirectory())->toBeNull()
        ->and($field->render()->name())->toBe('wire-forms::components.tiptap-editor');
});

test('tiptap editor custom toolbar and disabled buttons', function () {
    $field = TiptapEditor::make('content')
        ->toolbarButtons(['bold', 'italic', 'underline'])
        ->disableToolbarButtons(['underline']);

    expect($field->getToolbarButtons())->toBe(['bold', 'italic']);
});

test('tiptap editor disable all toolbar buttons', function () {
    $field = TiptapEditor::make('content')->disableAllToolbarButtons();

    expect($field->getToolbarButtons())->toBe([]);
});

test('tiptap editor output formats', function () {
    expect(TiptapEditor::make('c')->outputJson()->getOutputFormat())->toBe('json')
        ->and(TiptapEditor::make('c')->outputHtml()->getOutputFormat())->toBe('html');
});

test('tiptap editor extensions append toolbar buttons', function () {
    $field = TiptapEditor::make('content')
        ->withImages()
        ->withTables()
        ->withTextAlign()
        ->withHighlight()
        ->minHeight(320)
        ->maxLength(2000)
        ->fileAttachmentsDirectory('uploads');

    expect($field->isWithImages())->toBeTrue()
        ->and($field->isWithTables())->toBeTrue()
        ->and($field->isWithTextAlign())->toBeTrue()
        ->and($field->isWithHighlight())->toBeTrue()
        ->and($field->getMinHeight())->toBe(320)
        ->and($field->getMaxLength())->toBe(2000)
        ->and($field->getFileAttachmentsDirectory())->toBe('uploads')
        ->and($field->getToolbarButtons())->toContain('image', 'table', 'alignLeft', 'highlight');
});

test('tiptap editor disabling extensions does not append buttons', function () {
    $field = TiptapEditor::make('content')
        ->withImages(false)
        ->withTables(false)
        ->withTextAlign(false)
        ->withHighlight(false);

    expect($field->getToolbarButtons())->not->toContain('image')
        ->and($field->isWithImages())->toBeFalse();
});

test('tiptap editor alpine config exposes editor settings', function () {
    $field = TiptapEditor::make('content')->outputJson()->withImages();

    $config = $field->getAlpineConfig();

    expect($config)->toHaveKeys([
        'wireAttribute', 'outputFormat', 'disabled', 'readOnly',
        'placeholder', 'maxLength', 'withImages', 'withTables',
        'withTextAlign', 'withHighlight',
    ])
        ->and($config['outputFormat'])->toBe('json')
        ->and($config['withImages'])->toBeTrue();
});
