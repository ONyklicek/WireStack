<?php

declare(strict_types=1);

use Illuminate\Support\ViewErrorBag;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\Field;
use NyonCode\WireForms\Components\MarkdownEditor;
use NyonCode\WireForms\Components\RichEditor;
use NyonCode\WireForms\Components\TiptapEditor;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/**
 * All three editors titled their toolbars with bare `__('Bold')` keys, which
 * only an app-level translation file could ever answer: a Czech app shipped a
 * fully translated form with an English editor in the middle of it, and the
 * strings read inside JS — the link/image prompts — could not be translated at
 * all. They now share one vocabulary (`wire-forms::fields.editor.*`), so the
 * three read alike in every locale and a reworded button is reworded once.
 */
function renderEditorField(Field $field): string
{
    view()->share('errors', new ViewErrorBag);
    view()->share('_instance', new class
    {
        public function getId(): string
        {
            return 'test-component';
        }
    });

    return $field->render()->render();
}

/** MarkdownEditor binds through @entangle, so it needs a real Livewire render. */
class MarkdownEditorLocaleHost extends Component
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

it('titles the tiptap toolbar from the package vocabulary, not app-level keys', function () {
    $html = renderEditorField(TiptapEditor::make('content')->withTables());

    expect($html)->toContain('title="Bold"')
        ->and($html)->toContain('title="Table"')
        // Headings read as words, with the level interpolated.
        ->and($html)->toContain('title="Heading 1"');
});

it('translates the whole tiptap toolbar when the app runs in Czech', function () {
    app()->setLocale('cs');

    try {
        $html = renderEditorField(
            TiptapEditor::make('content')->withTables()->withTextAlign()->withHighlight()
        );

        expect($html)->toContain('title="Tučné"')
            ->and($html)->toContain('title="Kurzíva"')
            ->and($html)->toContain('title="Odrážkový seznam"')
            ->and($html)->toContain('title="Tabulka"')
            ->and($html)->toContain('title="Zarovnat na střed"')
            ->and($html)->toContain('title="Nadpis 2"')
            ->and($html)->toContain('title="Zpět"')
            // The button glyph is a glyph — it stays H1/H2/H3 in every locale.
            ->and($html)->toContain('>H1</span>');
    } finally {
        app()->setLocale('en');
    }
});

it('translates the rich editor toolbar from the same keys', function () {
    app()->setLocale('cs');

    try {
        // h2/h3 are not in the configured default toolbar, so ask for them.
        $html = renderEditorField(RichEditor::make('content')->toolbarButtons([
            'bold', 'italic', 'underline', 'strike', 'h2', 'h3',
            'bulletList', 'orderedList', 'link', 'blockquote', 'codeBlock', 'undo', 'redo',
        ]));

        expect($html)->toContain('title="Tučné"')
            ->and($html)->toContain('title="Podtržené"')
            ->and($html)->toContain('title="Číslovaný seznam"')
            ->and($html)->toContain('title="Nadpis 2"')
            ->and($html)->toContain('title="Nadpis 3"')
            ->and($html)->toContain('title="Blok kódu"')
            ->and($html)->toContain('title="Znovu"')
            // The link prompt lives inside x-data, and is translated too.
            ->and($html)->toContain("prompt('URL odkazu')");
    } finally {
        app()->setLocale('en');
    }
});

it('escapes the rich editor prompt so a translation cannot break its x-data', function () {
    app()->setLocale('cs');
    // A wording containing an apostrophe is exactly what the old
    // prompt('{{ __('Enter URL') }}') could not survive: `{{ }}` renders it as
    // &#039;, which decodes back to a quote and terminates the JS string — and
    // with it the double-quoted x-data attribute around it. @js() hex-escapes
    // both quote characters instead.
    app('translator')->addLines(['fields.editor.link_url' => "URL 'odkazu'"], 'cs', 'wire-forms');

    try {
        $html = renderEditorField(RichEditor::make('content'));

        expect($html)->toContain('prompt(\'URL \u0027odkazu\u0027\')')
            ->and($html)->not->toContain('&#039;');
    } finally {
        app()->setLocale('en');
    }
});

it('translates the markdown editor toolbar and its write/preview tabs', function () {
    app()->setLocale('cs');

    try {
        $html = Livewire::test(MarkdownEditorLocaleHost::class)->html();

        expect($html)->toContain('title="Tučné"')
            ->and($html)->toContain('title="Kurzíva"')
            ->and($html)->toContain('title="Kód v textu"')
            ->and($html)->toContain('title="Citace"')
            // The button inserts '## ', so it is a level-2 heading.
            ->and($html)->toContain('title="Nadpis 2"')
            ->and($html)->toContain('title="Odrážkový seznam"')
            ->and($html)->toContain('>Psát</button>')
            ->and($html)->toContain('>Náhled</button>');
    } finally {
        app()->setLocale('en');
    }
});

it('hands the JS its prompt titles already translated', function () {
    expect(TiptapEditor::make('content')->getAlpineConfig()['prompts'])
        ->toBe(['linkUrl' => 'Link URL', 'imageUrl' => 'Image URL']);

    app()->setLocale('cs');

    try {
        expect(TiptapEditor::make('content')->getAlpineConfig()['prompts'])
            ->toBe(['linkUrl' => 'URL odkazu', 'imageUrl' => 'URL obrázku']);
    } finally {
        app()->setLocale('en');
    }
});

it('keeps the editor vocabulary in step across locales', function () {
    $en = require __DIR__.'/../../resources/lang/en/fields.php';
    $cs = require __DIR__.'/../../resources/lang/cs/fields.php';

    expect(array_keys($cs['editor']))->toBe(array_keys($en['editor']))
        ->and($cs['editor'])->not->toBe($en['editor']);
});

it('leaves no editor toolbar string outside the shared vocabulary', function () {
    // The regression this whole change is about: a bare __('Bold') resolves
    // against the app's translations, never the package's, so it can only ever
    // be English here.
    $views = [
        __DIR__.'/../../resources/views/components/tiptap-editor.blade.php',
        __DIR__.'/../../resources/views/components/rich-editor.blade.php',
        __DIR__.'/../../resources/views/components/markdown-editor.blade.php',
    ];

    foreach ($views as $view) {
        expect(file_get_contents($view))->not->toMatch("/__\('[A-Z]/");
    }
});
