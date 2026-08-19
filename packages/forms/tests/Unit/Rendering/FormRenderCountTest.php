<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\CheckboxList;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/**
 * Render-count fuse for wire-forms (architecture/plans/forms-and-surfaces-performance.md
 * step 1).
 *
 * A form's render cost is dominated by the field, linearly — measured at roughly
 * 93% of a 25-field round trip, against 1.3 ms for an empty Livewire component. So
 * the thing worth pinning is the *slope*: how many `view()->render()` calls one
 * more field, one more option, or one more repeater item costs. An `@include`
 * dropped back inside a loop is invisible to every other test in this suite and
 * shows up only on a customer's Debugbar.
 *
 * How it counts: a wildcard view composer (`*`) fires once per view instance
 * rendered, including every `@include` and every `<x-…>` component, because
 * `@include` compiles to `make()->render()` and `View::renderContents()` calls
 * `callComposer()` on each render. So the counter sees the true per-item cost, not
 * just the field-component path. Same mechanism as the table's
 * `TableRenderCountTest`.
 *
 * Every number here is a slope measured between two sizes, never an absolute
 * count: `@once`, `@assets` and the icon cache all emit once per process, so
 * absolute counts drift with test order while slopes do not. The integers were
 * measured on this repository, not ported from the table — the table's own budget
 * comments went stale by 2.6–2.8× exactly that way.
 */

// ─── Host ────────────────────────────────────────────────────────────────────

class FrcHost extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = [];

    public string $shape = 'text';

    public int $fields = 1;

    public int $options = 2;

    public int $children = 2;

    public bool $creatable = false;

    public function mount(
        string $shape = 'text',
        int $fields = 1,
        int $options = 2,
        int $items = 0,
        int $children = 2,
        bool $creatable = false,
    ): void {
        $this->shape = $shape;
        $this->fields = $fields;
        $this->options = $options;
        $this->children = $children;
        $this->creatable = $creatable;

        if ($shape === 'repeater') {
            // Repeater rows come from state, so the item count is seeded here.
            $this->data['rows'] = array_fill(0, $items, ['a' => 'x', 'b' => 'y']);
        }
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema(match ($this->shape) {
            'checkbox-list' => [
                CheckboxList::make('picked')->options($this->optionMap()),
            ],
            'select' => array_map(
                fn (int $i) => $this->creatable
                    ? Select::make('s'.$i)->options($this->optionMap())
                        ->createOptionForm([TextInput::make('name')])
                    : Select::make('s'.$i)->options($this->optionMap()),
                range(1, $this->fields),
            ),
            'repeater' => [
                Repeater::make('rows')->schema(array_map(
                    fn (int $i) => TextInput::make(['a', 'b'][$i] ?? 'c'.$i),
                    range(0, $this->children - 1),
                )),
            ],
            default => array_map(
                fn (int $i) => TextInput::make('f'.$i),
                range(1, $this->fields),
            ),
        });
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }

    /** @return array<string, string> */
    private function optionMap(): array
    {
        return array_combine(
            array_map(fn (int $i) => 'o'.$i, range(1, $this->options)),
            array_map(fn (int $i) => 'Option '.$i, range(1, $this->options)),
        );
    }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Count every view render that happens while $render runs.
 *
 * The composer is registered fresh each call and writes to its own counter, so
 * calling this twice in one test does not cross-contaminate the returned values.
 */
function frcRenderCount(Closure $render): int
{
    $count = 0;

    View::composer('*', function () use (&$count): void {
        $count++;
    });

    $render();

    return $count;
}

/**
 * @param  array<string, mixed>  $params
 */
function frcRender(array $params): Closure
{
    return fn () => Livewire::test(FrcHost::class, $params)->html();
}

/**
 * Render once, discarding the count, to burn the process-wide scaffolding.
 *
 * `@once`, `@assets` and the `IconManager` cache all emit on first use only, so
 * whichever measurement runs first would otherwise carry a one-off that the
 * second does not — which reads as a bogus negative slope.
 *
 * @param  array<string, mixed>  $params
 */
function frcWarm(array $params): void
{
    frcRender($params)();
}

/**
 * The marginal view-render cost of one more unit, measured between two sizes.
 *
 * @param  array<string, mixed>  $small
 * @param  array<string, mixed>  $large
 */
function frcSlope(array $small, array $large, string $axis): float
{
    frcWarm($small);

    $delta = frcRenderCount(frcRender($large)) - frcRenderCount(frcRender($small));

    return $delta / ($large[$axis] - $small[$axis]);
}

// ─── The fuse ────────────────────────────────────────────────────────────────

it('costs exactly three view renders per ordinary field', function () {
    // The component view plus `partials.field-wrapper-start` plus
    // `field-wrapper-end` — the two wrapper includes are ~50% of a bare
    // TextInput's render time, and they are a documented public extension point
    // (docs/forms/custom-fields.md), so 3 is the intended floor, not an accident.
    //
    // This is the fuse's baseline: every other slope below is read against it.
    expect(frcSlope(
        ['shape' => 'text', 'fields' => 4],
        ['shape' => 'text', 'fields' => 12],
        'fields',
    ))->toEqual(3.0);
});

it('costs a flat six view renders per Select, independent of option count', function () {
    // Select is the most expensive ordinary field in the palette by render count.
    // Three of the six are the field view and its two wrappers; the rest is chrome
    // that must stay O(1) — in particular the options themselves are a loop of
    // markup, never a loop of `@include`.
    //
    // It was 7 until the option-modal partial was gated on
    // Select::hasMountedOptionModal(): that partial emits nothing unless a modal
    // is open, so every Select on every page was paying a render for zero bytes.
    $atFew = frcSlope(
        ['shape' => 'select', 'fields' => 2, 'options' => 3],
        ['shape' => 'select', 'fields' => 6, 'options' => 3],
        'fields',
    );

    $atMany = frcSlope(
        ['shape' => 'select', 'fields' => 2, 'options' => 30],
        ['shape' => 'select', 'fields' => 6, 'options' => 30],
        'fields',
    );

    expect($atFew)->toEqual(6.0)
        ->and($atMany)->toEqual($atFew);
});

it('renders the option-modal partial only while a modal is actually mounted', function () {
    // Half of the gate: a Select that *can* create options still costs nothing
    // extra while its modal is closed.
    expect(frcSlope(
        ['shape' => 'select', 'fields' => 2, 'options' => 3, 'creatable' => true],
        ['shape' => 'select', 'fields' => 6, 'options' => 3, 'creatable' => true],
        'fields',
    ))->toEqual(6.0);

    // The other half, and the one that would catch a gate that never opens:
    // mounting a modal must add renders. Both sides call mountCreateOption so the
    // extra round trip cancels out; only the control's state path resolves to no
    // field, which leaves the modal closed.
    $params = ['shape' => 'select', 'fields' => 6, 'options' => 3, 'creatable' => true];
    $mount = fn (string $path) => fn () => Livewire::test(FrcHost::class, $params)
        ->call('mountCreateOption', $path)
        ->html();

    frcWarm($params);
    $mount('data.s1')(); // warm the modal chrome too, for the same reason

    $closed = frcRenderCount($mount('data.nope'));
    $open = frcRenderCount($mount('data.s1'));

    expect($open)->toBeGreaterThan($closed);
});

it('costs zero view renders per CheckboxList option', function () {
    // `checkbox-list.blade.php` used to include `partials.checkbox-list-option`
    // from inside its `@foreach` — the only include written inside a loop
    // anywhere in the wire-forms view tree, and the N×View shape this fuse exists
    // to catch. It measured exactly 1.00 renders per option: 210 options, 216
    // views.
    //
    // The loop moved into `partials.checkbox-list-options`, which is included once
    // per grid, so the slope is zero and the list is O(1) in renders. Putting an
    // `@include` back inside the loop is what this catches.
    expect(frcSlope(
        ['shape' => 'checkbox-list', 'options' => 4],
        ['shape' => 'checkbox-list', 'options' => 20],
        'options',
    ))->toEqual(0.0);
});

it('costs one field-render per child per repeater item, and no per-item chrome', function () {
    // A repeater of R items × F fields is 3·R·F renders plus a constant. The
    // per-item cost is therefore entirely its children — measured, the row chrome
    // adds 0.12 ms and 2.7 KB over four bare fields, and a flat form of the same
    // 80 fields costs 95% of the milliseconds and 87% of the bytes.
    //
    // So this asserts the slope is exactly F × the ordinary-field cost: any
    // per-item wrapper that starts costing a render of its own shows up as a
    // remainder here.
    $twoChildren = frcSlope(
        ['shape' => 'repeater', 'items' => 2, 'children' => 2],
        ['shape' => 'repeater', 'items' => 6, 'children' => 2],
        'items',
    );

    $oneChild = frcSlope(
        ['shape' => 'repeater', 'items' => 2, 'children' => 1],
        ['shape' => 'repeater', 'items' => 6, 'children' => 1],
        'items',
    );

    expect($oneChild)->toEqual(3.0)
        ->and($twoChildren)->toEqual(6.0)
        ->and($twoChildren - $oneChild)->toEqual(3.0); // pure F, no per-item chrome
});
