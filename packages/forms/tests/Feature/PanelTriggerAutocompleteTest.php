<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\CheckboxList;
use NyonCode\WireForms\Components\ColorPicker;
use NyonCode\WireForms\Components\DateRangePicker;
use NyonCode\WireForms\Components\DateTimePicker;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\Tags;
use NyonCode\WireForms\Components\TimePicker;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/*
 * A text box that opens a panel, or searches inside one, is never autofilled.
 * Safari reads a state path such as `tableState.…` as an address field and
 * draws its AutoFill button in the box, and the first click goes to that
 * button instead of the panel. `autocomplete="off"` keeps the button out.
 */

class PanelTriggerAutocompleteHost extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                DateTimePicker::make('due')->asDateTime(),
                DateTimePicker::make('own')->extraInputAttributes(['autocomplete' => 'bday']),
                TimePicker::make('at'),
                DateRangePicker::make('period'),
                ColorPicker::make('brand'),
                Tags::make('labels'),
                Select::make('role')->options(['admin' => 'Admin'])->searchable(),
                CheckboxList::make('perms')->options(['read' => 'Read'])->searchable(),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

/** The opening `<input …>` tag carrying the given fragment. */
function panelInputTag(string $html, string $fragment): string
{
    preg_match_all('/<input\b[^>]*>/s', $html, $tags);

    foreach ($tags[0] as $tag) {
        if (str_contains($tag, $fragment)) {
            return $tag;
        }
    }

    throw new RuntimeException("No <input> carries [{$fragment}].");
}

it('turns autofill off in a box that opens or searches a panel', function (string $fragment) {
    $html = Livewire::test(PanelTriggerAutocompleteHost::class)->html();

    expect(panelInputTag($html, $fragment))->toContain('autocomplete="off"');
})->with([
    'date and time' => 'data-testid="form-datetime-data.due-trigger"',
    'time' => 'data-testid="form-time-data.at-trigger"',
    'date range' => 'data-testid="form-date-range-data.period_from-trigger"',
    'colour hex' => 'data-testid="form-color-data.brand-hex"',
    'tags' => 'x-model="input"',
    'select search' => 'data-testid="select-search"',
    'checkbox list search' => 'data-testid="form-checklist-data.perms-search"',
]);

it('lets an autocomplete the field was given win', function () {
    $tag = panelInputTag(
        Livewire::test(PanelTriggerAutocompleteHost::class)->html(),
        'data-testid="form-datetime-data.own-trigger"',
    );

    // HTML keeps the first occurrence of an attribute, so the app's stands first.
    expect(strpos($tag, 'autocomplete="bday"'))->toBeLessThan(strpos($tag, 'autocomplete="off"'));
});
