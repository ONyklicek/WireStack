<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components\Console;

use Illuminate\Console\Command;
use NyonCode\WireCore\Foundation\Console\Concerns\InteractsWithComponentScaffold;
use NyonCode\WireCore\Foundation\Console\Support\ComponentBlueprint;
use NyonCode\WireCore\Foundation\Console\Support\PublishedStubs;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Generates a custom form field and the Blade view of its input.
 *
 *   php artisan make:wire-field MoneyInput
 *     →  app/Forms/Components/MoneyInput.php
 *     →  resources/views/forms/components/money-input.blade.php
 *
 * No suffix is added: a field is named for the control it is — `MoneyInput`,
 * `RatingPicker` — the way the shipped ones are.
 */
#[AsCommand(name: 'make:wire-field')]
final class MakeFieldCommand extends Command
{
    use InteractsWithComponentScaffold;

    protected $signature = 'make:wire-field
        {name : The field, e.g. MoneyInput}
        {--f|force : Overwrite the class if it already exists; the view is never overwritten}';

    protected $description = 'Create a custom Wire form field and the Blade view of its input';

    public function handle(): int
    {
        return $this->scaffold(new ComponentBlueprint(
            stubs: new PublishedStubs('wire-forms', dirname(__DIR__, 3).'/stubs'),
            namespace: 'Forms\Components',
            suffix: '',
            classStub: 'field.stub',
            viewStub: 'field-view.stub',
            viewFolder: 'forms.components',
        ), "->schema([{class}::make('price')])");
    }
}
