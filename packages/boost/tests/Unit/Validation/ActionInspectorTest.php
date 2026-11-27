<?php

declare(strict_types=1);

use NyonCode\WireBoost\Support\Validation\ActionInspector;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireCore\Actions\ModalStep;
use NyonCode\WireForms\Components\TextInput;

beforeEach(function () {
    $this->inspector = new ActionInspector;
});

it('builds no form for something that is not an action, or an action without one', function () {
    expect($this->inspector->forms(ActionGroup::make([]), null))->toBe([])
        ->and($this->inspector->forms(Action::make('plain'), null))->toBe([]);
});

it('builds one form, or one per wizard step', function () {
    $single = Action::make('single')->form([TextInput::make('a')]);

    $wizard = Action::make('wizard')->steps([
        ModalStep::make('One')->schema([TextInput::make('a')]),
        ModalStep::make('Two')->schema([TextInput::make('b')]),
    ]);

    expect($this->inspector->forms($single, null))->toHaveCount(1)
        ->and($this->inspector->forms($wizard, null))->toHaveCount(2);
});

it('reads the record a table host exposes', function () {
    $host = new class
    {
        public function getRecord(): string
        {
            return 'row';
        }
    };

    expect($this->inspector->contextRecord($host))->toBe('row');
});

it('unwraps a wrapped record to the model inside it', function () {
    $host = new class
    {
        protected function resolveRecord(): object
        {
            return new class
            {
                public function unwrap(): string
                {
                    return 'native';
                }
            };
        }
    };

    expect($this->inspector->contextRecord($host))->toBe('native');
});

it('falls through an accessor that throws or answers nothing', function () {
    $host = new class
    {
        public function getRecord(): mixed
        {
            return null;
        }

        protected function resolveRecord(): never
        {
            throw new RuntimeException('no record');
        }
    };

    expect($this->inspector->contextRecord($host))->toBeNull()
        ->and($this->inspector->contextRecord(new stdClass))->toBeNull();
});

it('names actions and skips what has no name', function () {
    expect($this->inspector->names([Action::make('a'), ActionGroup::make([]), Action::make('a'), Action::make('b')]))
        ->toBe(['a', 'b']);
});
