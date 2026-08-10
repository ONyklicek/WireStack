<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\MarkdownEditor;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/**
 * The MarkdownEditor's Alpine component is written inline as an `x-data`
 * attribute, which means the HTML parser reads it before JavaScript does — and
 * both of the bugs below came from forgetting that.
 *
 * These assertions run against the attribute value *as the parser decodes it*,
 * i.e. exactly the string Alpine is handed. Asserting on the raw markup would
 * miss both: the source looked correct in each case.
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

/** The x-data expression as the browser's parser hands it to Alpine. */
function decodedXData(): string
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.Livewire::test(MarkdownEditorXDataHost::class)->html());

    foreach ((new DOMXPath($document))->query('//*[@x-data]') as $node) {
        $value = $node->getAttribute('x-data');

        if (str_contains($value, 'renderMd')) {
            return $value;
        }
    }

    return '';
}

it('hands Alpine the whole component, not a truncated one', function () {
    // A raw double quote inside the attribute ends it there — whatever the JS
    // means by it. One in a regex literal cut the expression mid-function, so
    // Alpine got `.replace(/\` and threw "Invalid regular expression: missing
    // /": no tab switch, no preview, no entangle — while the markup still
    // looked complete in the page source.
    $xData = decodedXData();

    expect($xData)->toContain('renderMd(text)')
        ->and($xData)->toContain('insertAround(before, after)')
        // The last method in the component: reaching it means nothing truncated.
        ->and($xData)->toContain('insertLine(prefix)');
});

it('escapes markdown into the preview instead of no-opping', function () {
    // The other half of the same trap: an entity is decoded, so '&amp;' written
    // once arrives as '&' and the sanitiser became replace(& with &) — a no-op
    // that let raw HTML through to x-html. The replacements have to be written
    // twice over to survive as text.
    $xData = decodedXData();

    expect($xData)->toContain(".replace(/&/g, '&amp;')")
        ->and($xData)->toContain(".replace(/</g, '&lt;')")
        ->and($xData)->toContain(".replace(/>/g, '&gt;')")
        ->and($xData)->toContain('.replace(/"/g, \'&quot;\')');
});

it('emits quoted attributes in the html it builds', function () {
    // The entity rewrite must not change what renderMd() produces: the anchor
    // and heading markup still carries ordinary double-quoted attributes.
    expect(decodedXData())
        ->toContain('<a href="')
        ->toContain('<h2 class="text-lg font-bold mt-4 mb-1">');
});
