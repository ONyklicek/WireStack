<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\DateTimePicker;
use NyonCode\WireForms\Components\MarkdownEditor;
use NyonCode\WireForms\Components\Rating;
use NyonCode\WireForms\Components\RichEditor;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\Tags;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\TimePicker;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/**
 * Payload fuse for the field types that used to inline a per-instance Alpine
 * `x-data` blob (architecture/plans/forms-and-surfaces-performance.md step 5).
 *
 * Seven field types each shipped their whole controller body again for every
 * instance on the page — a DateTimePicker measured 28.4 kB of HTML per field
 * against a TextInput's 1.6 kB, and on a 20-Select page the inlined blobs were
 * 45.7% of the document. The bodies are `Alpine.data()` registrations now and
 * only the per-instance config is markup.
 *
 * The budgets below are one-sided ceilings measured after that change, in the
 * spirit of the table's `TablePayloadFuseTest`: they pass by construction on the
 * day they are written and catch a body drifting back into the markup. They are
 * **marginal** bytes — the difference between N fields and N+K — so the form
 * chrome, the Livewire snapshot and any `@once` scaffolding drop out.
 *
 * Raw HTML bytes, deliberately, even though gzip collapses N near-identical
 * blocks by ~15.7×: what this guards is the *shape* of the markup, and the
 * browser's parse and morph cost is paid on the raw bytes.
 */
class FpHost extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = [];

    public string $type = 'text';

    public int $fields = 1;

    public function mount(string $type = 'text', int $fields = 1): void
    {
        $this->type = $type;
        $this->fields = $fields;
    }

    public function form(Form $form): Form
    {
        $options = ['a' => 'Alpha', 'b' => 'Beta', 'c' => 'Gamma'];

        return $form->statePath('data')->schema(array_map(fn (int $i) => match ($this->type) {
            'datetime' => DateTimePicker::make('f'.$i),
            'time' => TimePicker::make('f'.$i),
            'select' => Select::make('f'.$i)->options($options),
            'tags' => Tags::make('f'.$i),
            'rating' => Rating::make('f'.$i),
            'rich' => RichEditor::make('f'.$i),
            'markdown' => MarkdownEditor::make('f'.$i),
            default => TextInput::make('f'.$i),
        }, range(1, $this->fields)));
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

/** Marginal HTML bytes per field, netting out everything that is not per-field. */
function fpBytesPerField(string $type): float
{
    $small = strlen(Livewire::test(FpHost::class, ['type' => $type, 'fields' => 2])->html());
    $large = strlen(Livewire::test(FpHost::class, ['type' => $type, 'fields' => 6])->html());

    return ($large - $small) / 4;
}

it('keeps a field within its per-instance byte budget', function (string $type, int $ceiling) {
    expect(fpBytesPerField($type))->toBeLessThan($ceiling);
})->with([
    // TextInput is the control: it never had an x-data body, so its cost is what
    // a field's markup costs when nothing is inlined into it.
    'text' => ['text', 2_000],
    'datetime' => ['datetime', 16_000],
    'time' => ['time', 8_400],
    'select' => ['select', 8_200],
    'tags' => ['tags', 8_800],
    'rating' => ['rating', 10_400],
    'rich' => ['rich', 15_500],
    'markdown' => ['markdown', 7_400],
]);

it('no longer inlines a controller body into any field instance', function (string $type, string $marker) {
    // The body is gone from the markup when the factory is *called* and no
    // method of it is *defined* there. `init() {` is the marker because every one
    // of these controllers has an init and none of the surviving markup does.
    $html = Livewire::test(FpHost::class, ['type' => $type, 'fields' => 2])->html();

    expect($html)->toContain($marker.'(')
        ->and($html)->not->toContain('init() {');
})->with([
    'datetime' => ['datetime', 'wireDateTimePicker'],
    'time' => ['time', 'wireTimePicker'],
    'select' => ['select', 'wireSearchableSelect'],
    'tags' => ['tags', 'wireTagsInput'],
    'rating' => ['rating', 'wireRating'],
    'rich' => ['rich', 'wireRichEditor'],
    'markdown' => ['markdown', 'wireMarkdownEditor'],
]);

it('leaves the state binding in the markup, where the Alpine magic is in scope', function (string $type) {
    // $wire.entangle / @entangle are Alpine magics and are only in scope inside an
    // x-data expression, so `state:` cannot move into the bundle with the rest.
    // A controller that lost its binding would silently stop syncing.
    $html = Livewire::test(FpHost::class, ['type' => $type, 'fields' => 2])->html();

    expect($html)->toContain('state: ');
})->with(['datetime', 'time', 'select', 'tags', 'rating', 'markdown']);
