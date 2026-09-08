<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Plugin\Hooks\FormFillingPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Foundation\Enums\Hook;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;

/*
 * The way *in*.
 *
 * `form.saving` shapes what reaches the record and nothing shaped what reaches
 * the fields, so an application could add a field to a module's form and could
 * not change what an existing one arrives holding. This is that half.
 */

class FfhInvoice extends Model
{
    protected $guarded = [];
}

function ffhForm(): Form
{
    return Form::make()->schema([
        TextInput::make('number'),
        TextInput::make('currency'),
    ]);
}

it('lets a hook change what a field arrives holding', function () {
    app(PluginManager::class)->hook(
        Hook::FormFilling,
        function (FormFillingPayload $payload): FormFillingPayload {
            $payload->data['currency'] = 'CZK';

            return $payload;
        },
    );

    $state = ffhForm()->fill(['number' => 'INV-1'])->getState();

    expect($state['number'])->toBe('INV-1')
        ->and($state['currency'])->toBe('CZK');
});

it('hands the hook exactly what fill() was given', function () {
    $seen = null;

    app(PluginManager::class)->hook(
        Hook::FormFilling,
        function (FormFillingPayload $payload) use (&$seen): FormFillingPayload {
            $seen = $payload->data;

            return $payload;
        },
    );

    ffhForm()->fill(['number' => 'INV-1']);

    expect($seen)->toBe(['number' => 'INV-1']);
});

it('reaches state() too, which is fill() under another name', function () {
    app(PluginManager::class)->hook(
        Hook::FormFilling,
        function (FormFillingPayload $payload): FormFillingPayload {
            $payload->data['currency'] = 'CZK';

            return $payload;
        },
    );

    expect(ffhForm()->state(['number' => 'INV-1'])->getState()['currency'])->toBe('CZK');
});

it('does not fire for getInitialState, so an edit page asks once', function () {
    // Deliberate: `getInitialState()` answers what a control needs before
    // anything is bound, and an edit page calls both — a hook on each would fire
    // twice per page, which is how a callback that appends appends twice.
    $calls = 0;

    app(PluginManager::class)->hook(
        Hook::FormFilling,
        function (FormFillingPayload $payload) use (&$calls): FormFillingPayload {
            $calls++;

            return $payload;
        },
    );

    $form = ffhForm();
    $form->getInitialState();

    expect($calls)->toBe(0);

    $form->fill(['number' => 'INV-1']);

    expect($calls)->toBe(1);
});

it('scopes a filling hook by the form model', function () {
    app(PluginManager::class)->hook(
        Hook::FormFilling,
        function (FormFillingPayload $payload): FormFillingPayload {
            $payload->data['currency'] = 'CZK';

            return $payload;
        },
        for: FfhInvoice::class,
    );

    $scoped = ffhForm()->model(FfhInvoice::class)->fill(['number' => 'INV-1'])->getState();
    $unscoped = ffhForm()->fill(['number' => 'INV-1'])->getState();

    expect($scoped['currency'])->toBe('CZK')
        ->and($unscoped['currency'])->not->toBe('CZK');
});
