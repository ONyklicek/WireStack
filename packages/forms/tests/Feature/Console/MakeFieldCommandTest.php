<?php

declare(strict_types=1);

use App\Forms\Components\MoneyInput;
use Illuminate\Support\Facades\File;
use Illuminate\Support\MessageBag;

/*
 * make:wire-field — a field is named for the control it is, so no suffix, and
 * the generated view renders inside the shared field wrapper.
 */
afterEach(function () {
    File::deleteDirectory(app_path('Forms'));
    File::deleteDirectory(resource_path('views/forms'));
});

it('writes a field whose view binds its state inside the shared wrapper', function () {
    $this->artisan('make:wire-field', ['name' => 'MoneyInput'])
        ->expectsOutputToContain('Created [App\\Forms\\Components\\MoneyInput]')
        ->expectsOutputToContain("MoneyInput::make('price')")
        ->assertSuccessful();

    expect(resource_path('views/forms/components/money-input.blade.php'))->toBeFile();

    require_once app_path('Forms/Components/MoneyInput.php');

    $field = MoneyInput::make('price')->label('Price')->required();

    $html = view($field->render()->name(), ['field' => $field])->withErrors(new MessageBag)->render();

    expect($field->render()->name())->toBe('forms.components.money-input')
        ->and($field->getStateType())->toBe('string')
        ->and($html)->toContain('wire:model="'.$field->getWireModelAttribute().'"')
        ->toContain('Price')
        ->toContain('required');
});
