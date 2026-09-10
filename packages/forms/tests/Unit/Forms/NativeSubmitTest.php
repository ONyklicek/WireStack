<?php

declare(strict_types=1);

use Illuminate\Support\ViewErrorBag;
use NyonCode\WireCore\Core\Plugin\Hooks\FormConfiguringPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Foundation\Enums\Hook;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireForms\Components\Checkbox;
use NyonCode\WireForms\Components\Hidden;
use NyonCode\WireForms\Components\OtpInput;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Contracts\SupportsNativeSubmit;
use NyonCode\WireForms\Exceptions\FormConfigurationException;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Support\NativeSubmit;

/**
 * A field wrapper reads `$errors`, and a form renders outside a Livewire request
 * here, so the two things a request would have shared are shared by hand — the
 * same setup the other field-render tests use.
 */
beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
    view()->share('_instance', new class
    {
        public function getId(): string
        {
            return 'test-component';
        }
    });
});

// ─── The mode ──────────────────────────────────────────────────────

test('a form submits through Livewire unless told otherwise', function () {
    expect(Form::make()->submitsNatively())->toBeFalse();
});

test('the fields learn the mode from the form, not from themselves', function () {
    $email = TextInput::make('email');

    expect($email->submitsNatively())->toBeFalse();

    Form::make()->schema([$email])->nativeSubmit()->toHtml();

    expect($email->submitsNatively())->toBeTrue();
});

// ─── The binding ───────────────────────────────────────────────────

test('a native text input binds by name, never by state path', function () {
    // The dotted path is Livewire addressing; Fortify's request wants `email`.
    $field = TextInput::make('email')->statePath('data');

    expect($field->getWireModelAttribute())->toBe('data.email');

    $field->nativeSubmit();

    expect($field->getNativeBindingHtml())->toContain('name="email"')
        ->and($field->getNativeBindingHtml())->not->toContain('data.email');
});

test('a native input carries its value so a rejected submit comes back filled in', function () {
    $field = TextInput::make('email')->default('someone@example.com')->nativeSubmit();

    expect($field->getNativeBindingHtml())->toContain('value="someone@example.com"');
});

test('the value is escaped', function () {
    $field = TextInput::make('email')->default('" onfocus="alert(1)')->nativeSubmit();

    expect($field->getNativeBindingHtml())->not->toContain('onfocus="alert(1)')
        ->and($field->getNativeBindingHtml())->toContain('&quot;');
});

test('a field with nothing to prefill renders just its name', function () {
    expect(TextInput::make('email')->nativeSubmit()->getNativeBindingHtml())
        ->toBe('name="email"');
});

// ─── The guard, which is the point ─────────────────────────────────

test('a field that cannot submit natively is refused, by name and by type', function () {
    expect(fn () => Form::make()->schema([
        TextInput::make('email'),
        Select::make('country')->options(['cz' => 'Czechia']),
    ])->nativeSubmit()->toHtml())
        ->toThrow(
            FormConfigurationException::class,
            'Field [country] of type [NyonCode\WireForms\Components\Select] cannot render for a native submit'
        );
});

test('a repeating layout is refused, not walked into', function () {
    // A `Repeater` is a `LayoutComponent`, so a guard that recurses into every
    // layout walks past it into its template children and lets the repeater
    // itself render into a native form — where it dies as "Using $this when not
    // in object context", from a view, naming nothing. The question that avoids
    // that is not "is this a layout" but "does it carry state".
    expect(fn () => NativeSubmit::prepare([
        Repeater::make('rows')->schema([TextInput::make('label')]),
    ]))->toThrow(FormConfigurationException::class, 'Field [rows]');
});

test('a grouping layout is still walked into', function () {
    // The other half of the same question: a Section holds no state, so its
    // children are ordinary fields and must be switched, not refused.
    $inner = TextInput::make('email');

    NativeSubmit::prepare([Section::make('Account')->schema([$inner])]);

    expect($inner->submitsNatively())->toBeTrue();
});

test('a stray value in a schema is passed over, not refused', function () {
    // A schema is `array<int, mixed>` and FormRuntime skips what is not a
    // component; the guard matches it rather than turning a loose value into a
    // "cannot submit natively" error about something that is not a field.
    expect(fn () => NativeSubmit::prepare(['not a component', 42, null]))
        ->not->toThrow(FormConfigurationException::class);
});

test('a field nested inside a layout gets the mode too', function () {
    $email = TextInput::make('email');
    $section = Section::make('Account')->schema([$email]);

    NativeSubmit::prepare([$section]);

    expect($email->submitsNatively())->toBeTrue();
});

test('the refusal reaches fields nested inside a layout', function () {
    $section = Section::make('Account')
        ->schema([Select::make('country')->options([])]);

    expect(fn () => NativeSubmit::prepare([$section]))
        ->toThrow(FormConfigurationException::class, 'Field [country]');
});

test('support is declared, never assumed — only the fields with a consumer have it', function () {
    // ADR 0036 §6: this grows by named consumer. The signed-out screens need a
    // text input and a checkbox — the credentials and "stay signed in"; the rest
    // arrive when a screen needs them.
    expect(TextInput::make('a'))->toBeInstanceOf(SupportsNativeSubmit::class)
        ->and(Checkbox::make('b'))->toBeInstanceOf(SupportsNativeSubmit::class)
        ->and(Hidden::make('c'))->toBeInstanceOf(SupportsNativeSubmit::class)
        ->and(OtpInput::make('d'))->toBeInstanceOf(SupportsNativeSubmit::class)
        ->and(Select::make('e'))->not->toBeInstanceOf(SupportsNativeSubmit::class);
});

// ─── A checkbox answers with its presence ──────────────────────────

test('a native checkbox posts a constant and answers by being there', function () {
    // Not `value="1"` because true is 1, but because an unticked box posts no
    // key at all — which is the boolean a browser can actually express.
    expect(Checkbox::make('remember')->nativeSubmit()->getNativeBindingHtml())
        ->toBe('name="remember" value="1"');
});

test('a checkbox defaulted on starts ticked', function () {
    expect(Checkbox::make('remember')->default(true)->nativeSubmit()->getNativeBindingHtml())
        ->toBe('name="remember" value="1" checked');
});

/**
 * What a rejected submission left behind, on the request the way a real one has
 * it: `old()` reads the session *through the request*, so a store put in place
 * without binding it is a fixture no browser produces.
 *
 * @param  array<string, mixed>  $input
 */
function withOldInput(array $input): void
{
    $session = app('session')->driver();
    $session->put('_old_input', $input);

    request()->setLaravelSession($session);
}

test('a submission that came back with the box cleared leaves it cleared', function () {
    // The trap this exists for: `old('remember')` is null both for a fresh page
    // and for a box the user just unticked, so a default of true would silently
    // re-tick it. The question is whether there is *any* old input.
    withOldInput(['email' => 'someone@example.com']);

    expect(Checkbox::make('remember')->default(true)->nativeSubmit()->getNativeBindingHtml())
        ->toBe('name="remember" value="1"');
});

test('a submission that came back with the box ticked keeps it ticked', function () {
    withOldInput(['remember' => '1']);

    expect(Checkbox::make('remember')->nativeSubmit()->getNativeBindingHtml())
        ->toBe('name="remember" value="1" checked');
});

test('a rejected sign-in comes back with the address still in the field', function () {
    // The half a hand-written login form forgets, and the reason a user retypes
    // an address because the password was wrong.
    withOldInput(['email' => 'someone@example.com']);

    expect(TextInput::make('email')->nativeSubmit()->getNativeBindingHtml())
        ->toBe('name="email" value="someone@example.com"');
});

test('a native checkbox renders its name and no wire:model', function () {
    $html = Form::make()->schema([Checkbox::make('remember')])->nativeSubmit()->toHtml();

    expect($html)->toContain('name="remember" value="1"')
        ->and($html)->not->toContain('wire:model');
});

// ─── A hidden value has nowhere to live but the document ───────────

test('a hidden field renders nothing in a Livewire form', function () {
    // Unchanged, and documented: the value lives in form state, so there is
    // nothing for the DOM to hold.
    expect(Form::make()->schema([Hidden::make('token')])->toHtml())
        ->not->toContain('type="hidden"');
});

test('a hidden field renders in a native form, because state is not an option there', function () {
    // No Livewire, no snapshot: an unrendered hidden field is a value silently
    // dropped from the request.
    $html = Form::make()->schema([Hidden::make('token')->default('the-token')])
        ->nativeSubmit()
        ->toHtml();

    expect($html)->toContain('type="hidden"')
        ->and($html)->toContain('name="token" value="the-token"')
        ->and($html)->not->toContain('wire:model');
});

test('a hidden field still keeps the user out of it', function () {
    // The constructor's promise is "the user has no business choosing this",
    // which native mode does not weaken — only where the value can live changes.
    expect(Hidden::make('token')->nativeSubmit()->isHidden())->toBeTrue();
});

test('a hidden field validates, which is what its own docs promise', function () {
    // Skipping it made `->rules([...])` on a Hidden a rule that never ran, while
    // its value was filled, carried in an editable snapshot and saved anyway.
    $rules = Form::make()->schema([
        Hidden::make('type')->default('post')->rules(['in:post,page']),
    ])->getValidationRules();

    expect($rules)->toHaveKey('type')
        ->and($rules['type'])->toContain('in:post,page');
});

// ─── The Livewire path is untouched ────────────────────────────────

test('a form left alone still renders wire:model and no name', function () {
    $html = Form::make()->schema([TextInput::make('email')])->toHtml();

    expect($html)->toContain('wire:model')
        ->and($html)->not->toContain('name="email"');
});

test('a native form renders name and no wire:model', function () {
    $html = Form::make()->schema([TextInput::make('email')])->nativeSubmit()->toHtml();

    expect($html)->toContain('name="email"')
        ->and($html)->not->toContain('wire:model');
});

// ─── The mode reaches what a plugin added, not only what was declared ──

test('a field a hook added to the schema submits natively too', function () {
    // The schema a native form renders is not always the schema it was declared
    // with: `form.configuring` runs in between. A field switched on the declared
    // one would have left this one bound by `wire:model` — an input that looks
    // right and posts nothing, which is the failure ADR 0036 §2 refuses.
    $added = TextInput::make('tenant');

    app(PluginManager::class)->hook(Hook::FormConfiguring, function (FormConfiguringPayload $payload) use ($added): void {
        $payload->schema = [...$payload->schema, $added];
    });

    $html = Form::make()->schema([TextInput::make('email')])->nativeSubmit()->toHtml();

    expect($added->submitsNatively())->toBeTrue()
        ->and($html)->toContain('name="tenant"')
        ->and($html)->not->toContain('wire:model');
});

test('a field a hook added that cannot submit natively is refused like any other', function () {
    app(PluginManager::class)->hook(Hook::FormConfiguring, function (FormConfiguringPayload $payload): void {
        $payload->schema = [...$payload->schema, Select::make('country')->options([])];
    });

    expect(fn () => Form::make()->schema([TextInput::make('email')])->nativeSubmit()->toHtml())
        ->toThrow(FormConfigurationException::class, 'Field [country]');
});

test('switching the mode after something read the config still reaches the fields', function () {
    // The config is memoized, and the mode is decided on the schema behind it.
    $form = Form::make()->schema([TextInput::make('email')]);
    $form->getFlatComponents();

    expect($form->nativeSubmit()->toHtml())->toContain('name="email"');
});

// ─── Boxes that behave like one field ──────────────────────────────

test('an OTP field posts through one named input, never through its boxes', function () {
    // Six boxes with names would post six values. The boxes are the display;
    // the field is the input beside them.
    $html = Form::make()->schema([OtpInput::make('code')->length(6)])
        ->nativeSubmit()
        ->toHtml();

    expect(substr_count($html, 'name="code"'))->toBe(1)
        ->and(substr_count($html, 'data-testid="form-otp-code-'))->toBe(7);
});

test('the OTP boxes hide the input they drive, and only where Alpine can', function () {
    // `x-show="false"` rather than `type="hidden"`: with no Alpine on the page
    // the input stays visible and typeable, which is what keeps a two-factor
    // challenge answerable with JavaScript off.
    $html = Form::make()->schema([OtpInput::make('code')])->nativeSubmit()->toHtml();

    expect($html)->toContain('x-show="false"')
        ->and($html)->toContain(':value="digits.join(\'\')"')
        ->and($html)->not->toContain('type="hidden"');
});

test('the OTP controller is told which mode it is in, and what came back', function () {
    // It reads `$wire` in the Livewire mode and must not touch it in the other,
    // so the flag is config rather than something the controller sniffs.
    withOldInput(['code' => '123456']);

    $html = Form::make()->schema([OtpInput::make('code')])->nativeSubmit()->toHtml();

    expect($html)->toContain('native: true')
        ->and($html)->toContain("value: '123456'")
        // …and the input the boxes drive comes back holding it too.
        ->and($html)->toContain('name="code" value="123456"');
});

test('an OTP field in a Livewire form is unchanged — boxes, state, no name', function () {
    $html = Form::make()->schema([OtpInput::make('code')])->toHtml();

    expect($html)->toContain('native: false')
        ->and($html)->not->toContain('name="code"')
        ->and($html)->not->toContain('x-show="false"');
});
