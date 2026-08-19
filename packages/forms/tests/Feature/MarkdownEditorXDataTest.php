<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\MarkdownEditor;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;
use NyonCode\WireForms\WireFormsServiceProvider;

/**
 * The MarkdownEditor's Alpine component used to be written inline as an `x-data`
 * attribute, which meant the HTML parser read it before JavaScript did — and two
 * bugs came from forgetting that: a raw `"` in a regex literal ended the
 * attribute and truncated the component, and an entity written once decoded to
 * `replace(& with &)`, a no-op that let raw HTML reach `x-html`.
 *
 * The body is `wireMarkdownEditor` in `wire-forms-fields.js` now, so the parser
 * never sees it and neither trap can recur. What still has to hold is the
 * property those bugs threatened — the sanitiser really escapes — and the
 * structural claim that the body is out of the markup. The first is asserted
 * against the shipped bundle rather than the source, which is also what catches
 * a `resources/js` edit that was never rebuilt
 * (`npm run build:forms-assets`); the same shape as `SelectionAssetTest`.
 */
class MarkdownEditorXDataHost extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = [];

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([MarkdownEditor::make('notes')]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

function fieldsBundle(): string
{
    $bundle = WireFormsServiceProvider::ASSETS_PATH.'/wire-forms-fields.js';

    expect(is_file($bundle))->toBeTrue();

    return (string) file_get_contents($bundle);
}

it('keeps the whole editor body out of the markup', function () {
    // The attribute-truncation trap is retired by construction: there is no
    // multi-line JS in the attribute to truncate. This pins that.
    $html = Livewire::test(MarkdownEditorXDataHost::class)->html();

    expect($html)->toContain('wireMarkdownEditor(')
        ->and($html)->not->toContain('renderMd(text)')
        ->and($html)->not->toContain('insertLine(prefix)');
});

it('ships a sanitiser that escapes every HTML metacharacter', function () {
    // The security half of the old trap, asserted where the code now lives.
    // Reading the built bundle also fails if `resources/js` was edited and
    // `npm run build:forms-assets` was not run.
    expect(fieldsBundle())
        ->toContain('&amp;')
        ->toContain('&lt;')
        ->toContain('&gt;')
        ->toContain('&quot;')
        // The link-scheme allowlist that blocks javascript:/data: URLs.
        ->toContain('mailto:');
});

it('ships the built bundle with every field controller registered', function () {
    // One bundle carries all six forms controllers; a missing registration is a
    // dead `x-data` and an Alpine "is not defined" at runtime, which Pest cannot
    // otherwise see.
    expect(fieldsBundle())
        ->toContain('alpine:init')
        ->toContain('wireMarkdownEditor')
        ->toContain('wireDateTimePicker')
        ->toContain('wireTimePicker')
        ->toContain('wireTagsInput')
        ->toContain('wireRating')
        ->toContain('wireRichEditor');
});
