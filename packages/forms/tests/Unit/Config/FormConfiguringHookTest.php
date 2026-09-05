<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Plugin\Hooks\FormConfiguringPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Foundation\Enums\Hook;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;

/*
 * The half of the additive story forms did not have.
 *
 * `table.configuring` let a plugin add a column to a list it does not own; there
 * was no counterpart for the form beside it, so "adjust an installed module
 * rather than fork it" stopped at the edge of the table.
 */

class FchInvoice extends Model
{
    protected $table = 'fch_invoices';
}

/**
 * The field names a form ends up built from — read through the config, which is
 * where the hook runs.
 *
 * @return array<int, string>
 */
function fchSchema(Form $form): array
{
    return array_map(
        static fn (object $component): ?string => $component->getName(),
        $form->getFlatComponents(),
    );
}

it('lets a plugin add a field to a form it does not own', function () {
    app(PluginManager::class)->hook(
        Hook::FormConfiguring,
        function (FormConfiguringPayload $payload): FormConfiguringPayload {
            $payload->schema = [...$payload->schema, TextInput::make('internal_note')];

            return $payload;
        },
    );

    $form = Form::make()->schema([TextInput::make('number')]);

    expect(fchSchema($form))->toBe(['number', 'internal_note']);
});

it('runs once per form, because the config it feeds is memoized', function () {
    $calls = 0;

    app(PluginManager::class)->hook(Hook::FormConfiguring, function (FormConfiguringPayload $payload) use (&$calls) {
        $calls++;

        return $payload;
    });

    $form = Form::make()->schema([TextInput::make('number')]);

    fchSchema($form);
    fchSchema($form);

    expect($calls)->toBe(1);
});

it('scopes a callback to the model a form edits', function () {
    // What makes this usable in an application with several modules installed:
    // the callback belongs to one of them.
    $touched = [];

    app(PluginManager::class)->hook(
        Hook::FormConfiguring,
        function (FormConfiguringPayload $payload) use (&$touched): FormConfiguringPayload {
            $touched[] = $payload->target?->model;
            $payload->schema = [...$payload->schema, TextInput::make('internal_note')];

            return $payload;
        },
        for: FchInvoice::class,
    );

    $invoiceForm = Form::make()->model(FchInvoice::class)->schema([TextInput::make('number')]);
    $otherForm = Form::make()->schema([TextInput::make('number')]);

    expect(fchSchema($invoiceForm))->toBe(['number', 'internal_note'])
        ->and(fchSchema($otherForm))->toBe(['number'])
        ->and($touched)->toBe([FchInvoice::class]);
});

it('builds its schema unchanged when nothing is bound to run hooks', function () {
    // A form composed outside a booted application. Form::make() resolves
    // through the container, so the container is swapped only once it exists.
    $form = Form::make()->schema([TextInput::make('number')]);
    $booted = Container::getInstance();

    try {
        Container::setInstance(new Container);

        expect(fchSchema($form))->toBe(['number']);
    } finally {
        Container::setInstance($booted);
    }
});
