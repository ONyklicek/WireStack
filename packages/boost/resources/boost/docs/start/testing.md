---
order: 60
summary: Three levels of test — the plain object, the Livewire component, and the rendered page — and which failures each one catches.
---

# Testing

Wire components are plain PHP objects that render to HTML, so they are
straightforward to test. There are three useful levels:

| Level | Use for | Needs Livewire? |
|-------|---------|-----------------|
| **Standalone** | Validation rules, save logic, configuration | No |
| **Livewire** | State binding, actions, errors, the full request cycle | Yes |
| **Unit** | A single field/column's API and rendered output | No |

The packages are tested with [Pest](https://pestphp.com). The examples below use
Pest syntax, but everything works the same in plain PHPUnit.

---

## Testing Forms Standalone

A `Form` works without Livewire, which makes validation and save logic the
fastest thing to test. `validate()` returns the validated data or throws
`Illuminate\Validation\ValidationException`.

```php
use Illuminate\Validation\ValidationException;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;

it('validates required fields', function () {
    $data = Form::make()
        ->schema([
            TextInput::make('name')->required(),
            TextInput::make('email')->rules(['email'])->required(),
        ])
        ->state(['name' => 'John', 'email' => 'john@example.com'])
        ->validate();

    expect($data)->toBe(['name' => 'John', 'email' => 'john@example.com']);
});

it('rejects a missing required field', function () {
    $form = Form::make()
        ->schema([TextInput::make('name')->required()])
        ->state(['name' => '']);

    expect(fn () => $form->validate())->toThrow(ValidationException::class);
});
```

Assert on collected rules and state without running validation:

```php
$form = Form::make()
    ->statePath('data')
    ->schema([TextInput::make('name')->required()->maxLength(255)]);

expect($form->getValidationRules())->toHaveKey('data.name');
```

Test the save path against the database by giving the form a model:

```php
use App\Models\User;

it('creates a user on save', function () {
    Form::make()
        ->model(User::class)
        ->schema([
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required(),
        ])
        ->state(['name' => 'Jane', 'email' => 'jane@example.com'])
        ->save();

    expect(User::where('email', 'jane@example.com')->exists())->toBeTrue();
});
```

Use `model($instance)` instead of `model(User::class)` to test edit mode
(`save()` calls `update()`), and `isCreating()` / `isEditing()` to assert the
mode.

---

## Testing Forms in Livewire

To exercise state binding, the save action, and validation errors as a user
would hit them, mount the host component with `Livewire::test()`. State lives
under the form's `statePath`, so you set and assert nested keys like
`data.name`.

```php
use Livewire\Livewire;

it('shows a validation error for a missing name', function () {
    Livewire::test(CreateUser::class)
        ->set('data.email', 'jane@example.com')
        ->call('save')
        ->assertHasErrors('data.name');
});

it('saves when the form is valid', function () {
    Livewire::test(CreateUser::class)
        ->set('data.name', 'Jane')
        ->set('data.email', 'jane@example.com')
        ->call('save')
        ->assertHasNoErrors()
        ->assertOk();

    expect(User::where('email', 'jane@example.com')->exists())->toBeTrue();
});
```

The host component is just a Livewire component using `WithForms`:

```php
use Livewire\Component;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class CreateUser extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form->statePath('data')->model(User::class)->schema([ // [tl! focus:start]
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required(),
        ]); // [tl! focus:end]
    }

    public function save(): void
    {
        $this->form->save();
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}
```

Repeater and other field actions are Livewire calls, so test them with
`->call()`:

```php
Livewire::test(CreateUser::class)
    ->call('addRepeaterItem', 'data.contacts')
    ->assertCount('data.contacts', 1);
```

---

## Testing Tables in Livewire

A table is a Livewire component using `WithTable`. Its UI state (search, filters,
sort, pagination) lives on the synthesized `tableState` property, so you drive it
by setting nested paths like `tableState.search`. Row actions run through
`executeTableAction($recordKey, $actionName)`.

```php
use Livewire\Livewire;

it('lists and searches records', function () {
    Invoice::factory()->create(['number' => 'INV-100']);
    Invoice::factory()->create(['number' => 'INV-200']);

    Livewire::test(InvoicesTable::class)
        ->assertOk()
        ->assertSee('INV-100')
        ->set('tableState.search', 'INV-100')
        ->assertSee('INV-100')
        ->assertDontSee('INV-200');
});

it('runs a row action', function () {
    $invoice = Invoice::factory()->create(['status' => 'draft']);

    Livewire::test(InvoicesTable::class)
        ->call('executeTableAction', (string) $invoice->getKey(), 'publish');

    expect($invoice->fresh()->status)->toBe('published');
});
```

Bulk actions use `executeBulkAction($actionName)` after selecting rows, and
actions that open a modal go through `openActionModal()` then
`submitActionModal()`. If an assertion does not match, dump the component with
`->dump()` to inspect the live `tableState`.

---

## Unit-Testing a Custom Field

When you write a [custom field](../forms/custom-fields.md), test its API and
rendered output directly — no Livewire required.

```php
use App\Forms\Components\MoneyInput;

it('exposes its configuration and renders the currency', function () {
    $field = MoneyInput::make('price')->currency('EUR')->decimals(2);

    expect($field->getCurrency())->toBe('EUR')
        ->and($field->getStateType())->toBe('int')
        ->and((string) $field->toHtml())->toContain('EUR');
});
```

The same approach works for custom columns, filters, and actions: build the
object, call its setters, and assert on its getters or `toHtml()`.

---

## Testing Plugins

Instantiate `PluginManager` directly to test registration, boot, and hooks. See
[Core Plugins → Testing Plugins](../core/plugins/examples.md#testing-plugins) for the full
pattern.

```php
use NyonCode\WireCore\Core\Plugin\PluginManager;

it('adds updated_by before save', function () {
    $manager = new PluginManager();
    $manager->register(new FormAuditPlugin());

    $payload = $manager->runHook('form.saving', ['data' => ['name' => 'Jane']]);

    expect($payload['data'])->toHaveKey('updated_by');
});
```

---

## Testing a Tour

Which [tour](../core/tours.md) runs is decided on the server, so the parts worth
testing need no browser: that it is registered, that it claims the screen you
meant it to, and that somebody who has already seen it is left alone. What a
test at this level cannot see is the walkthrough itself — whether the panel
lands beside the right element, whether a hidden step is skipped, whether
Escape closes it. That is the browser's behaviour.

Registration is a container lookup, and the cheapest guard against a provider
that stopped booting:

```php
use NyonCode\WireCore\Tours\Tours;

it('registers the onboarding tour', function () {
    expect(app(Tours::class)->has('getting-started'))->toBeTrue();
});
```

Whether it runs on a given screen is a page render. The panel carries the
`tour-panel` element hook, which is public API — it is not renamed or removed in
a minor release — so it is a safe thing to assert on:

```php
it('walks a new user through the orders list', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/orders')
        ->assertSee('data-wire="tour-panel"', false);
});
```

The second argument turns escaping off; without it the quotes in the attribute
will not match. A tour that does not claim the screen renders nothing at all, so
the negative is the same assertion inverted — which is how you pin scoping:

```php
use NyonCode\WireCore\Tours\TourState;

it('leaves somebody who has already finished it alone', function () {
    $user = User::factory()->create();

    app(TourState::class)->acknowledge('getting-started', $user);   // what Finish and Skip record

    $this->actingAs($user)
        ->get('/admin/orders')
        ->assertDontSee('data-wire="tour-panel"', false);
});
```

`TourState::replay()` is the same call the other way round, and the one your own
[replay trigger](../core/tours.md#your-own-trigger) makes.

Two things that trip tests up rather than applications:

- **The store is per driver.** Tours default to the `session` driver, and each
  test gets a fresh session, so acknowledgements do not leak between tests. On
  `database` the `wire_preferences` migration has to have run, or the
  acknowledgement is written nowhere and the tour keeps appearing.
- **A tour needs the layout that draws it.** The page render above asserts
  through `wire-admin`'s layout. A test that renders a component in isolation
  has no `PageChrome`, and therefore no panel, however well the tour is scoped.

---

## Running the Suite

```bash
composer test            # everything
composer test:core
composer test:forms
composer test:table
composer test:sortable

# Cross-package runtime, state, macros, plugins
vendor/bin/pest --configuration phpunit.xml --testsuite "Integration"
```

Run the **owning package** first when you change one package, then the
**downstream** package(s), and the Integration suite when state, rendering,
macros, or plugin wiring changed.

---

## See Also

- [Extending Forms](../forms/custom-fields.md) — building the fields you are testing
- [Save Lifecycle](../forms/save-lifecycle.md) — the hooks `save()` runs through
- [Core Plugins](../core/plugins/index.md) — hook and plugin testing
- [Tour](../core/tours.md) — how a tour decides to run, and what it stores
