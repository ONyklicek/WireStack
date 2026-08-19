<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/**
 * `Form::fieldPartials()` — answering a field update with the fields that moved
 * (architecture/plans/forms-and-surfaces-performance.md step 10).
 *
 * A `wire:model` commit re-renders the host. Measured on a 12-field form: 19 860 B
 * of HTML to carry one field's 1 562 B, 12.7× raw and 2.3× gzipped, plus the
 * browser morphing all of it.
 *
 * The safety comes from comparing rendered markup rather than reasoning about
 * dependencies — the same shape as `WithTable::queueChangedRowPartials()`. Every
 * test below is about a case where reasoning would have got it wrong.
 */
class FieldPartialHost extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = ['name' => 'Ada', 'kind' => 'a', 'role' => null];

    public bool $partials = true;

    public bool $dependent = false;

    public bool $conditional = false;

    public function mount(bool $partials = true, bool $dependent = false, bool $conditional = false): void
    {
        $this->partials = $partials;
        $this->dependent = $dependent;
        $this->conditional = $conditional;
    }

    public function form(Form $form): Form
    {
        $schema = [TextInput::make('name'), TextInput::make('kind')];

        if ($this->dependent) {
            // Its options read another field's state, which is the case a
            // dependency graph is most likely to miss.
            $schema[] = Select::make('role')->options(
                fn (): array => ['x' => 'Role for '.($this->data['name'] ?? '')],
            );
        }

        if ($this->conditional) {
            $schema[] = TextInput::make('role')->visibleWhen('kind', 'b');
        }

        return $form->statePath('data')->fieldPartials($this->partials)->schema($schema);
    }

    public function render(): string
    {
        return '<div><span id="outside">{{ $this->data["name"] }}</span>{{ $this->form }}</div>';
    }
}

/** The effects of a commit, after one that establishes the baseline stamps. */
function fieldPartialCommit(array $params, string $path, mixed $value): array
{
    $component = Livewire::test(FieldPartialHost::class, $params);

    // The first update has nothing to compare against and renders fully; that is
    // the documented behaviour, and the baseline for everything below.
    $component->set($path, $value.'-baseline');

    return $component->set($path, $value)->effects;
}

it('emits an anchor per field only when the form asked for one', function () {
    expect(Livewire::test(FieldPartialHost::class)->html())
        ->toContain('wire:partial="field-data.name"')
        ->toContain('wire:partial="field-data.kind"');

    expect(Livewire::test(FieldPartialHost::class, ['partials' => false])->html())
        ->not->toContain('wire:partial=');
});

it('answers an ordinary keystroke commit with nothing at all', function () {
    // The common case, and it is not an edge case. A field's value rides
    // `wire:model` and the data payload rather than its markup — a TextInput
    // renders no `value` attribute — so committing one moves no markup anywhere.
    // Neither a view nor a region is worth sending.
    $effects = fieldPartialCommit([], 'data.name', 'Grace');

    expect($effects['html'] ?? null)->toBeNull()
        ->and($effects['wirePartials'] ?? null)->toBeNull();
});

it('sends a sibling whose options read the updated field, which no graph was asked about', function () {
    // The Select's options() closure reads data.name. Its markup therefore moves
    // when data.name does, and comparing markup is what notices.
    $effects = fieldPartialCommit(['dependent' => true], 'data.name', 'Grace');

    // Only the Select moved: the edited field's own markup never changes.
    expect(array_keys($effects['wirePartials'] ?? []))->toBe(['field-data.role'])
        ->and($effects['wirePartials']['field-data.role'])->toContain('Role for Grace');

    // The view is skipped by `PartialRenderHook::call()`, which needs a call to
    // run — and `Testable::set()` sends updates with `calls: []`. A browser never
    // does: `wire:model.live` sends the synthetic `$commit` alongside its updates
    // (livewire.esm.js `expression.startsWith('$parent') ? … : component.$wire.$commit()`),
    // which is why the skip is asserted in workbench/scripts/verify-field-partials.mjs
    // rather than here. Where the view does run, the response carries `html` and
    // the client applier defers to it — correct, just not saved.
});

it('falls back to a full render when a field appears', function () {
    // visibleWhen: the set of fields changed, which is the page's shape moving.
    // No set of regions describes that, so nothing is sent partially.
    $effects = fieldPartialCommit(['conditional' => true], 'data.kind', 'b');

    expect($effects['wirePartials'] ?? null)->toBeNull()
        ->and($effects['html'] ?? null)->not->toBeNull();
});

it('renders fully while the form has not opted in', function () {
    $effects = fieldPartialCommit(['partials' => false], 'data.name', 'Grace');

    expect($effects['wirePartials'] ?? null)->toBeNull()
        ->and($effects['html'] ?? null)->not->toBeNull();
});

it('renders fully on the first update, having nothing to compare against', function () {
    $effects = Livewire::test(FieldPartialHost::class)->set('data.name', 'Grace')->effects;

    expect($effects['wirePartials'] ?? null)->toBeNull()
        ->and($effects['html'] ?? null)->not->toBeNull();
});

it('carries the anchor in the markup it sends, or the next update finds nothing to morph', function () {
    $markup = fieldPartialCommit(['dependent' => true], 'data.name', 'Grace')['wirePartials']['field-data.role'];

    expect($markup)->toContain('wire:partial="field-data.role"')
        ->and($markup)->toStartWith('<div');
});
