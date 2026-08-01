<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\Block;
use NyonCode\WireForms\Components\Builder;
use NyonCode\WireForms\Components\Textarea;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Exceptions\FormConfigurationException;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class BuilderComponent extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = [
        'content' => [
            ['type' => 'heading', 'data' => ['text' => 'Hello']],
            ['type' => 'paragraph', 'data' => ['body' => 'World']],
        ],
    ];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Builder::make('content')
                    ->reorderable()
                    ->blocks([
                        Block::make('heading')->icon('star')->schema([
                            TextInput::make('text')->rules(['required']),
                        ]),
                        Block::make('paragraph')->schema([
                            Textarea::make('body'),
                        ]),
                    ]),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

// ─── Schema selection ───────────────────────────────────────────────────────

it('edits each item with its own block schema', function () {
    $html = Livewire::test(BuilderComponent::class)->html();

    // Item 0 is a heading, item 1 a paragraph — each binds only its own field.
    expect($html)->toContain('data.content.0.data.text')
        ->and($html)->toContain('data.content.1.data.body')
        ->and($html)->not->toContain('data.content.0.data.body');
});

it('nests item fields under data so a field named type cannot clash', function () {
    $builder = Builder::make('content');

    expect($builder->getItemStatePath(0))->toBe('content.0.data');
});

it('renders nothing for an item whose block no longer exists', function () {
    // Stored content outlives the code that declared it; a renamed block must
    // not make the whole form unrenderable.
    $html = Livewire::test(BuilderComponent::class, [
        'data' => ['content' => [['type' => 'removed-block', 'data' => ['text' => 'x']]]],
    ])->html();

    expect($html)->toContain('removed-block')
        ->and($html)->not->toContain('data.content.0.data.text');
});

it('resolves declared blocks by name and nothing else', function () {
    $builder = Builder::make('content')->blocks([Block::make('heading')]);

    expect($builder->getBlock('heading'))->toBeInstanceOf(Block::class)
        ->and($builder->getBlock('missing'))->toBeNull()
        ->and($builder->getBlocks())->toHaveCount(1);
});

it('reads the stored type only from a well-formed item', function () {
    $builder = Builder::make('content');

    expect($builder->getItemType(['type' => 'heading']))->toBe('heading')
        ->and($builder->getItemType(['type' => '']))->toBeNull()
        ->and($builder->getItemType(['data' => []]))->toBeNull()
        ->and($builder->getItemType('nonsense'))->toBeNull();
});

// ─── Mutation ───────────────────────────────────────────────────────────────

it('adds an item of the chosen block type', function () {
    Livewire::test(BuilderComponent::class)
        ->call('addBuilderItem', 'data.content', 'paragraph')
        ->assertCount('data.content', 3)
        ->assertSet('data.content.2', ['type' => 'paragraph', 'data' => []]);
});

it('removes and reorders through the shared repeater endpoints', function () {
    Livewire::test(BuilderComponent::class)
        ->call('reorderRepeaterItems', 'data.content', [1, 0])
        ->assertSet('data.content.0.type', 'paragraph')
        ->call('removeRepeaterItem', 'data.content', 0)
        ->assertCount('data.content', 1)
        ->assertSet('data.content.0.type', 'heading');
});

it('offers one picker entry per declared block', function () {
    $html = Livewire::test(BuilderComponent::class)->html();

    expect($html)->toContain("addBuilderItem('data.content', 'heading')")
        ->and($html)->toContain("addBuilderItem('data.content', 'paragraph')");
});

// ─── Validation ─────────────────────────────────────────────────────────────

it('mounts block rules under the item data envelope', function () {
    $builder = Builder::make('content')->blocks([
        Block::make('heading')->schema([TextInput::make('text')->rules(['required'])]),
        Block::make('paragraph')->schema([Textarea::make('body')]),
    ]);

    expect($builder->getItemValidationRules())->toBe([
        'data.text' => ['required'],
        // A field with no rules still needs an entry, or its value is dropped
        // from the validated data before it is saved.
        'data.body' => ['nullable'],
    ]);
});

it('reports its own path plus a wildcard so item errors map back to it', function () {
    expect(Builder::make('content')->getDescendantFieldStatePaths())
        ->toBe(['content', 'content.*']);
});

// ─── Misuse ─────────────────────────────────────────────────────────────────

it('says so when a block is placed in a schema directly', function () {
    Block::make('heading')->render();
})->throws(FormConfigurationException::class, 'cannot be placed in a schema directly');

// Inherited from Repeater, table() would have been accepted and ignored: the
// layout heads one column per schema field, and every block has its own schema.
it('refuses the table layout, which cannot apply to mixed blocks', function () {
    Builder::make('content')->table();
})->throws(FormConfigurationException::class, 'cannot use table()');
