<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\CheckboxList;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/**
 * Byte-identity guard for moving `checkbox-list-option`'s `@include` out of the
 * option loop (architecture/plans/forms-and-surfaces-performance.md step 6).
 *
 * The render-count win is pinned by `FormRenderCountTest`; this is the other half
 * of the trade. The option markup must come out exactly as it did when each
 * option was its own `@include` — same classes, same ids, same `data-testid`s,
 * same searchable `x-show`, same order — across the flat layout, the grouped
 * layout, and the searchable and disabled variants, because those are the four
 * things the partial branched on.
 */
class ChkMarkupHost extends Component
{
    /** @var array<string, mixed> */
    public array $data = ['food' => ['pear']];

    public string $mode = 'flat';

    use WithForms;

    public function mount(string $mode = 'flat'): void
    {
        $this->mode = $mode;
    }

    public function form(Form $form): Form
    {
        $options = ['apple' => 'Apple', 'pear' => 'Pear', 'leek' => 'Leek'];

        $field = match ($this->mode) {
            'grouped' => CheckboxList::make('food')->groups([
                'Fruit' => ['apple' => 'Apple', 'pear' => 'Pear'],
                'Veg' => ['leek' => 'Leek'],
            ]),
            'searchable' => CheckboxList::make('food')->options($options)->searchable(),
            'disabled' => CheckboxList::make('food')->options($options)->disabled(),
            'columns' => CheckboxList::make('food')->options($options)->columns(3),
            default => CheckboxList::make('food')->options($options),
        };

        return $form->statePath('data')->schema([$field]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

function chkHtml(string $mode): string
{
    return Livewire::test(ChkMarkupHost::class, ['mode' => $mode])->html();
}

/** Collapse whitespace so indentation changes do not read as markup changes. */
function chkFlat(string $mode): string
{
    return (string) preg_replace('/\s+/', ' ', chkHtml($mode));
}

it('renders one checkbox and one label per option, in order', function (string $mode) {
    $html = chkFlat($mode);

    foreach (['apple' => 'Apple', 'pear' => 'Pear', 'leek' => 'Leek'] as $value => $label) {
        expect($html)
            ->toContain('id="data.food-'.$value.'"')
            ->toContain('data-testid="form-checklist-data.food-'.$value.'"')
            ->toContain('value="'.$value.'"')
            ->toContain('<label for="data.food-'.$value.'" class="text-sm text-gray-700 dark:text-gray-300"> '.$label.' </label>');
    }

    // Order is the option map's order, not a re-sort.
    expect(strpos($html, 'food-apple'))->toBeLessThan(strpos($html, 'food-pear'))
        ->and(strpos($html, 'food-pear'))->toBeLessThan(strpos($html, 'food-leek'));
})->with(['flat', 'grouped', 'searchable', 'disabled', 'columns']);

it('keeps the per-option wrapper and its grid intact', function () {
    expect(chkFlat('flat'))
        ->toContain('<div class="grid gap-2 grid-cols-1">')
        ->toContain('<div class="flex items-center gap-2" >')
        ->and(substr_count(chkFlat('flat'), 'class="flex items-center gap-2"'))->toBe(3)
        // columns(3) still reaches the grid the partial now owns.
        ->and(chkFlat('columns'))->toContain('grid-cols-1 sm:grid-cols-3');
});

it('emits the search filter on each option only when searchable', function () {
    // x-show tests that option's own label, so it is genuinely per-option and has
    // to stay inside the loop wherever the loop lives.
    expect(substr_count(chkFlat('searchable'), 'x-show="!search ||'))->toBe(3)
        ->and(chkFlat('searchable'))->toContain("x-show=\"!search || 'pear'.includes(search.toLowerCase())\"")
        ->and(chkFlat('flat'))->not->toContain('x-show="!search ||');
});

it('disables every option when the field is disabled', function () {
    expect(substr_count(chkFlat('disabled'), 'value="apple" disabled'))->toBe(1)
        ->and(substr_count(chkFlat('disabled'), ' disabled '))->toBe(3)
        ->and(chkFlat('flat'))->not->toContain(' disabled ');
});

it('keeps each group in its own grid', function () {
    $html = chkHtml('grouped');

    expect(substr_count($html, 'form-checklist-data.food-group'))->toBe(2)
        // Fruit's two options sit before the Veg heading, Veg's one after it.
        ->and(strpos($html, 'food-pear'))->toBeLessThan(strpos($html, 'Veg'))
        ->and(strpos($html, 'Veg'))->toBeLessThan(strpos($html, 'food-leek'));
});
