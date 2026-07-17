<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Core\Actions\ActionResult;
use NyonCode\WireCore\Core\Plugin\Hooks\ActionExecutedPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\ActionExecutingPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\State\StateContainer;
use NyonCode\WireTable\Concerns\TableStateSchema;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

it('dispatches action plugin hooks around action pipeline execution', function () {
    $manager = app(PluginManager::class);
    $events = [];

    $manager->hook('action.executing', function (array $payload) use (&$events) {
        $events[] = 'hook.executing:'.$payload['actionName'].':'.$payload['actionType'];

        expect($payload['data'])->toBe(['note' => 'queued'])
            ->and($payload['component'])->toBeObject();

        return $payload;
    });

    $manager->hook('action.executed', function (array $payload) use (&$events) {
        $events[] = 'hook.executed:'.$payload['actionName'].':'.$payload['actionType'];

        expect($payload['result'])->toBeInstanceOf(ActionResult::class)
            ->and($payload['result']->isSuccess())->toBeTrue()
            ->and($payload['component'])->toBeObject();

        return $payload;
    });

    $component = new class
    {
        use WithTable;

        public function __construct()
        {
            $this->tableState = new StateContainer(TableStateSchema::defaults());
        }

        public function table(Table $table): Table
        {
            return $table;
        }

        public function runTestAction(Action $action): void
        {
            $this->executeActionPipeline(
                action: $action,
                payload: ['data' => ['note' => 'queued']],
                haltKey: '__header__',
                actionType: 'header',
            );
        }

        protected function handleActionSuccess(mixed $action, mixed $record = null): void
        {
            //
        }
    };

    $action = Action::make('archive')->action(function () use (&$events) {
        $events[] = 'action';

        return ActionResult::success();
    });

    $component->runTestAction($action);

    expect($events)->toBe([
        'hook.executing:archive:header',
        'action',
        'hook.executed:archive:header',
    ]);
});

it('dispatches typed action plugin hooks with payload DTOs', function () {
    $manager = app(PluginManager::class);
    $typedEvents = [];

    $manager->hook('action.executing', function (ActionExecutingPayload $payload) use (&$typedEvents) {
        $typedEvents[] = 'typed.executing:'.$payload->actionName.':'.$payload->actionType;

        expect($payload->context->formData)->toBe(['note' => 'queued'])
            ->and($payload->component)->toBeObject();

        return $payload;
    });

    $manager->hook('action.executed', function (ActionExecutedPayload $payload) use (&$typedEvents) {
        $typedEvents[] = 'typed.executed:'.$payload->actionName.':'.$payload->actionType;

        expect($payload->result)->toBeInstanceOf(ActionResult::class)
            ->and($payload->result->isSuccess())->toBeTrue()
            ->and($payload->component)->toBeObject();

        return $payload;
    });

    $component = new class
    {
        use WithTable;

        public function __construct()
        {
            $this->tableState = new StateContainer(TableStateSchema::defaults());
        }

        public function table(Table $table): Table
        {
            return $table;
        }

        public function runTestAction(Action $action): void
        {
            $this->executeActionPipeline(
                action: $action,
                payload: ['data' => ['note' => 'queued']],
                haltKey: '__header__',
                actionType: 'header',
            );
        }

        protected function handleActionSuccess(mixed $action, mixed $record = null): void
        {
            //
        }
    };

    $action = Action::make('archive')->action(function () {
        return ActionResult::success();
    });

    $component->runTestAction($action);

    expect($typedEvents)->toBe([
        'typed.executing:archive:header',
        'typed.executed:archive:header',
    ]);
});
