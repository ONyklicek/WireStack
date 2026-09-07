<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\CheckboxList;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/*
 * `showSelected()` — what is chosen, above the list that hides it.
 *
 * The half a checklist gives up to a multi-select: a long list only shows the
 * rows near the scroll position and a searched one only the matches, so "what
 * have I actually picked" ends up off screen. The chips read the entangled
 * state rather than the ticked boxes, because the boxes that are not rendered
 * would be missing from the count exactly when the chips are worth having.
 *
 * Rendered through a real host — `@entangle` compiles to a `$__livewire` lookup,
 * so this cannot render standalone.
 */

class ChecklistChipsHost extends Component
{
    use WithForms;

    public array $data = ['permissions' => []];

    public bool $chips = true;

    public bool $disabled = false;

    public function form(Form $form): Form
    {
        $field = CheckboxList::make('permissions')
            ->options(['invoices.view' => 'invoices.view', 'invoices.*' => 'invoices.*'])
            ->showSelected($this->chips)
            ->disabled($this->disabled);

        return $form->statePath('data')->schema([$field]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

it('is off until it is asked for', function () {
    // On a list of five options the chips are a second copy of the same five
    // words, so this stays a decision somebody makes.
    expect(CheckboxList::make('p')->isShowingSelected())->toBeFalse()
        ->and(CheckboxList::make('p')->showSelected()->isShowingSelected())->toBeTrue()
        ->and(CheckboxList::make('p')->showSelected()->showSelected(false)->isShowingSelected())->toBeFalse();
});

it('renders the chip row over the entangled state', function () {
    $html = Livewire::test(ChecklistChipsHost::class)->assertOk()->html();

    expect($html)->toContain('data-testid="form-checklist-data.permissions-selected"')
        // The chips are drawn from state, not from the checkboxes: a row that is
        // scrolled out or filtered away is still a chip.
        ->toContain('x-for="chosen in selected"')
        ->toContain('.entangle(')
        // And they are removable, which is the fastest way to undo a wrong tick
        // without hunting for it in two hundred rows.
        ->toContain('remove(chosen.value)');
});

it('draws nothing at all where the chips were not asked for', function () {
    $html = Livewire::test(ChecklistChipsHost::class, ['chips' => false])->assertOk()->html();

    expect($html)->not->toContain('form-checklist-data.permissions-selected')
        // And no entangle either: it compiles to a `$__livewire` lookup, so
        // emitting it unconditionally would make every checkbox list in the
        // stack renderable only inside a Livewire component.
        ->and($html)->not->toContain('.entangle(');
});

it('offers no way to unpick from a disabled list', function () {
    // The chips would otherwise be a second, working control over a field the
    // form has closed.
    $html = Livewire::test(ChecklistChipsHost::class, ['disabled' => true])->assertOk()->html();

    expect($html)->toContain('form-checklist-data.permissions-selected')
        ->not->toContain('remove(chosen.value)');
});
