<?php

declare(strict_types=1);

use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;

/*
 * The second-best extension path, which used to be missing everywhere the first
 * one was.
 *
 * ADR 0030 measured `use Macroable` in exactly two classes — `Table` and
 * `BaseAction` — so an application could add vocabulary to a table it did not
 * write and not to the form beside it. A macro is what you reach for when you
 * *do* hold the reference and only want new words for it; a hook is for a
 * component you never see.
 */

it('lets an application add vocabulary to Form', function () {
    Form::macro('tenantScoped', function (): Form {
        /** @var Form $this */
        return $this->statePath('tenant');
    });

    expect(Form::hasMacro('tenantScoped'))->toBeTrue()
        ->and(Form::make()->tenantScoped())->toBeInstanceOf(Form::class);
});

it('lets an application add vocabulary to a field', function () {
    // Declared on the base class, so every field type inherits it — which is the
    // reason it goes there rather than on each concrete field.
    TextInput::macro('slug', function (): TextInput {
        /** @var TextInput $this */
        return $this->label('Slug');
    });

    expect(TextInput::make('name')->slug()->getLabel())->toBe('Slug');
});
