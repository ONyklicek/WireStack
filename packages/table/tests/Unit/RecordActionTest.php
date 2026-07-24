<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireTable\Exceptions\TableConfigurationException;
use NyonCode\WireTable\Support\RecordAction;
use NyonCode\WireTable\Support\RecordTrigger;
use NyonCode\WireTable\Table;

// ─── RecordAction value object ───────────────────────────────────

it('wraps an action and exposes its name', function () {
    $binding = RecordAction::make(Action::make('edit')->label('Edit'));

    expect($binding->getName())->toBe('edit')
        ->and($binding->getAction())->toBeInstanceOf(Action::class)
        ->and($binding->isReference())->toBeFalse();
});

it('references an action by name without wrapping an instance', function () {
    $binding = RecordAction::make('edit');

    expect($binding->getName())->toBe('edit')
        ->and($binding->getAction())->toBeNull()
        ->and($binding->isReference())->toBeTrue();
});

// ─── Table registration ──────────────────────────────────────────

it('has no record actions until one is bound', function () {
    expect(Table::make()->hasRecordActions())->toBeFalse()
        ->and(Table::make()->getRecordActions())->toBe([]);
});

it('appends record actions with the singular binder', function () {
    $table = Table::make()
        ->recordAction('view')
        ->recordAction(RecordAction::make(Action::make('edit')));

    expect($table->hasRecordActions())->toBeTrue()
        ->and($table->getRecordActions())->toHaveCount(2);
});

it('replaces the bindings with the plural setter', function () {
    $table = Table::make()
        ->recordAction('view')
        ->recordActions([RecordAction::make('edit')]);

    expect($table->getRecordActions())->toHaveCount(1)
        ->and($table->getRecordActions()[0])->toBeInstanceOf(RecordAction::class);
});

it('stores a string reference verbatim for later resolution', function () {
    $table = Table::make()->recordAction('edit');

    expect($table->getRecordActions())->toBe(['edit']);
});

// ─── Guard: RecordAction is not a toolbar action ─────────────────

it('rejects a RecordAction passed to actions()', function () {
    Table::make()->actions([RecordAction::make('edit')]);
})->throws(TableConfigurationException::class, 'cannot be registered in actions()');

it('still accepts plain actions in actions()', function () {
    $table = Table::make()->actions([Action::make('edit')]);

    expect($table->getActions())->toHaveCount(1);
});

// ─── Hover / active-row styling knobs ────────────────────────────

it('defaults the record-action hover to neutral (null)', function () {
    expect(Table::make()->getRecordActionHover())->toBeNull();
});

it('opts into a record-action hover color', function () {
    expect(Table::make()->recordActionHover('primary')->getRecordActionHover())->toBe('primary')
        ->and(Table::make()->recordActionHover('')->getRecordActionHover())->toBeNull();
});

it('overrides the active-row class', function () {
    expect(Table::make()->activeRowClass('bg-amber-100')->getActiveRowClass())->toBe('bg-amber-100')
        ->and(Table::make()->activeRowClass('')->getActiveRowClass())->toBeNull()
        ->and(Table::make()->getActiveRowClass())->toBeNull();
});

// ─── Triggers ────────────────────────────────────────────────────

it('records a double-click trigger', function () {
    $binding = RecordAction::make('edit')->onDoubleClick();

    expect($binding->getTriggers())->toHaveCount(1)
        ->and($binding->getTriggers()[0])->toBeInstanceOf(RecordTrigger::class)
        ->and($binding->getTriggers()[0]->type)->toBe(RecordTrigger::DOUBLE_CLICK)
        ->and($binding->hasTrigger(RecordTrigger::DOUBLE_CLICK))->toBeTrue()
        ->and($binding->hasTrigger(RecordTrigger::CLICK))->toBeFalse();
});

it('records several triggers on one binding', function () {
    $binding = RecordAction::make('edit')->onDoubleClick()->onKey('Enter');

    expect($binding->getTriggers())->toHaveCount(2)
        ->and($binding->hasTrigger(RecordTrigger::DOUBLE_CLICK))->toBeTrue()
        ->and($binding->hasTrigger(RecordTrigger::KEY))->toBeTrue();
});

it('binds a custom gesture by name (extensibility seam)', function () {
    $binding = RecordAction::make('edit')->on('triple-click');

    expect($binding->hasTrigger('triple-click'))->toBeTrue()
        ->and($binding->getTriggers()[0]->type)->toBe('triple-click');
});

it('carries the key on a key trigger', function () {
    $trigger = RecordAction::make('edit')->onKey('Delete')->getTriggers()[0];

    expect($trigger->type)->toBe(RecordTrigger::KEY)
        ->and($trigger->key)->toBe('Delete');
});

// ─── onKey delegates to the canonical keyboard shortcut ──────────

it('stamps keyboardShortcut on the wrapped action via onKey', function () {
    $binding = RecordAction::make(Action::make('remove'))->onKey('Delete');

    expect($binding->getAction()->getKeyboardShortcut())->toBe('Delete');
});

it('keeps the key on a name reference without an action to stamp', function () {
    $binding = RecordAction::make('remove')->onKey('Delete');

    expect($binding->getAction())->toBeNull()
        ->and($binding->getTriggers()[0]->key)->toBe('Delete');
});

// ─── behaviorOnly vs. alsoInRowActions ───────────────────────────

it('is behaviour-only by default', function () {
    $binding = RecordAction::make('edit')->onDoubleClick();

    expect($binding->isBehaviorOnly())->toBeTrue()
        ->and($binding->rendersInRowActions())->toBeFalse();
});

it('opts into rendering as a row-action button, then back out', function () {
    $binding = RecordAction::make('edit')->alsoInRowActions();
    expect($binding->rendersInRowActions())->toBeTrue()
        ->and($binding->isBehaviorOnly())->toBeFalse();

    $binding->behaviorOnly();
    expect($binding->rendersInRowActions())->toBeFalse()
        ->and($binding->isBehaviorOnly())->toBeTrue();
});

// ─── __call delegation to the wrapped action ─────────────────────

it('forwards fluent setters to the wrapped action and stays chainable', function () {
    $binding = RecordAction::make(Action::make('edit'))
        ->label('Edit record')
        ->onDoubleClick();

    // Setter returns the wrapper (chainable), getter passes the value through.
    expect($binding)->toBeInstanceOf(RecordAction::class)
        ->and($binding->getLabel())->toBe('Edit record')
        ->and($binding->getAction()->getLabel())->toBe('Edit record');
});

it('throws when configuring a name-referenced binding', function () {
    RecordAction::make('edit')->label('Edit');
})->throws(TableConfigurationException::class, 'no action of its own to configure');

// ─── Macro promotion on Action ───────────────────────────────────

it('promotes a fluent Action into a RecordAction via macro', function () {
    $binding = Action::make('edit')->label('Edit')->onDoubleClick();

    expect($binding)->toBeInstanceOf(RecordAction::class)
        ->and($binding->getName())->toBe('edit')
        ->and($binding->hasTrigger(RecordTrigger::DOUBLE_CLICK))->toBeTrue()
        ->and($binding->getAction()->getLabel())->toBe('Edit');
});

it('promotes via onClick / onContextMenu / on / onKey macros', function () {
    expect(Action::make('a')->onClick())->toBeInstanceOf(RecordAction::class)
        ->and(Action::make('b')->onContextMenu()->hasTrigger(RecordTrigger::CONTEXT_MENU))->toBeTrue()
        ->and(Action::make('c')->on('long-press')->hasTrigger('long-press'))->toBeTrue();

    $keyBinding = Action::make('d')->onKey('Enter');
    expect($keyBinding->hasTrigger(RecordTrigger::KEY))->toBeTrue()
        ->and($keyBinding->getAction()->getKeyboardShortcut())->toBe('Enter');
});

it('lets a promoted binding go straight into recordActions()', function () {
    $table = Table::make()->recordActions([
        Action::make('view')->onClick(),
        Action::make('edit')->onDoubleClick(),
    ]);

    expect($table->getRecordActions())->toHaveCount(2)
        ->and($table->getRecordActions()[0])->toBeInstanceOf(RecordAction::class);
});

// ─── Resolver: pointer map + selection-aware default (F2) ────────

it('maps explicit pointer triggers to action names', function () {
    $table = Table::make()->recordActions([
        RecordAction::make('view')->onClick(),
        RecordAction::make('edit')->onDoubleClick(),
        RecordAction::make('menu')->onContextMenu(),
        RecordAction::make('rm')->onKey('Delete'),
    ]);

    // Context-menu and key triggers are excluded from the pointer map.
    expect($table->getRecordActionBindings())->toBe(['click' => 'view', 'dblclick' => 'edit']);
});

it('defaults a triggerless binding to single click, or double click when selectable', function () {
    expect(Table::make()->recordAction('view')->getRecordActionBindings())
        ->toBe(['click' => 'view']);

    expect(Table::make()->selectable()->recordAction('view')->getRecordActionBindings())
        ->toBe(['dblclick' => 'view']);
});

it('lets a later binding win the same trigger', function () {
    $table = Table::make()->recordActions([
        RecordAction::make('view')->onClick(),
        RecordAction::make('open')->onClick(),
    ]);

    expect($table->getRecordActionBindings())->toBe(['click' => 'open']);
});

// ─── Resolver: reference resolution ──────────────────────────────

it('resolves a name reference to the very action in actions()', function () {
    $edit = Action::make('edit');
    $table = Table::make()->actions([$edit]);

    expect($table->findRegisteredAction('edit'))->toBe($edit)
        ->and($table->findRegisteredAction('missing'))->toBeNull();
});

it('exposes wrapped record-action instances for the execution fallback', function () {
    $edit = Action::make('edit');
    $table = Table::make()->recordActions([RecordAction::make($edit), 'view']);

    // The string reference contributes no instance; only the wrapped action does.
    expect($table->getRecordActionInstances())->toBe([$edit]);
});

// ─── Context-menu unification ────────────────────────────────────

it('feeds an onContextMenu record action into the context menu', function () {
    $table = Table::make()->recordAction(RecordAction::make(Action::make('menu'))->onContextMenu());

    expect($table->hasRowContextMenu())->toBeTrue()
        ->and($table->getContextMenuActions())->toHaveCount(1);
});

it('merges a referenced onContextMenu action with the dedicated menu', function () {
    $edit = Action::make('edit');
    $table = Table::make()
        ->actions([$edit])
        ->rowContextMenu([Action::make('archive')])
        ->recordAction(RecordAction::make('edit')->onContextMenu());

    $actions = $table->getContextMenuActions();
    expect($actions)->toHaveCount(2)
        ->and($actions[1])->toBe($edit); // referenced instance, resolved from actions()
});

// ─── alsoInRowActions vs behaviorOnly in the render list ─────────

it('renders alsoInRowActions bindings as toolbar buttons, not behaviorOnly ones', function () {
    $table = Table::make()
        ->actions([Action::make('delete')])
        ->recordActions([
            RecordAction::make(Action::make('edit'))->onDoubleClick()->alsoInRowActions(),
            RecordAction::make(Action::make('view'))->onClick()->behaviorOnly(),
        ]);

    $names = array_map(fn ($a) => $a->getName(), $table->getRowActionsForDisplay());

    expect($names)->toContain('delete')->toContain('edit')->not->toContain('view');
});

it('does not double a referenced action already in the toolbar', function () {
    $edit = Action::make('edit');
    $table = Table::make()
        ->actions([$edit])
        ->recordAction(RecordAction::make('edit')->onDoubleClick()->alsoInRowActions());

    $names = array_map(fn ($a) => $a->getName(), $table->getRowActionsForDisplay());

    expect(array_filter($names, fn ($n) => $n === 'edit'))->toHaveCount(1);
});

it('counts an alsoInRowActions record button as having actions', function () {
    expect(Table::make()->recordAction(RecordAction::make(Action::make('edit'))->alsoInRowActions())->hasActions())
        ->toBeTrue()
        ->and(Table::make()->recordAction(RecordAction::make(Action::make('edit'))->behaviorOnly())->hasActions())
        ->toBeFalse();
});

// ─── Row styling: cursor + record-action hover (F4) ──────────────

it('marks a pointer-record-action row as clickable', function () {
    expect(Table::make()->hasRecordActionPointer())->toBeFalse();

    $table = Table::make()->recordAction(RecordAction::make('edit')->onDoubleClick());
    expect($table->hasRecordActionPointer())->toBeTrue()
        ->and($table->getRowClasses(null, 0))->toContain('cursor-pointer');
});

it('adds no cursor to a context-menu-only table (no pointer binding)', function () {
    $table = Table::make()->recordAction(RecordAction::make(Action::make('menu'))->onContextMenu());

    expect($table->hasRecordActionPointer())->toBeFalse()
        ->and($table->getRowClasses(null, 0))->not->toContain('cursor-pointer');
});

it('keeps the neutral hover by default and opts into a colored one', function () {
    $neutral = Table::make()->recordAction(RecordAction::make('edit')->onDoubleClick());
    expect($neutral->getRowClasses(null, 0))
        ->toContain('hover:bg-gray-50')
        ->not->toContain('hover:bg-primary-50');

    $colored = Table::make()
        ->recordActionHover('primary')
        ->recordAction(RecordAction::make('edit')->onDoubleClick());
    expect($colored->getRowClasses(null, 0))
        ->toContain('hover:bg-primary-50')
        ->not->toContain('hover:bg-gray-50');
});

it('does not apply the record-action hover without a pointer binding', function () {
    // Hover override set, but the only record action is a context menu → the row
    // is not clickable, so the neutral hover stays.
    $table = Table::make()
        ->recordActionHover('primary')
        ->recordAction(RecordAction::make(Action::make('menu'))->onContextMenu());

    expect($table->getRowClasses(null, 0))
        ->toContain('hover:bg-gray-50')
        ->not->toContain('hover:bg-primary-50');
});

it('lets a tinted row keep its tint hover but still be clickable', function () {
    // getRowClasses only enters the tint branch for a real record (null short-circuits).
    $record = new class extends Model {};

    $table = Table::make()
        ->rowColor('danger')
        ->recordActionHover('primary')
        ->recordAction(RecordAction::make('edit')->onDoubleClick());

    $classes = $table->getRowClasses($record, 0);
    // Tint owns the hue; the record-action hover override does not fight it.
    expect($classes)->toContain('bg-red-50')
        ->toContain('cursor-pointer')
        ->not->toContain('hover:bg-primary-50');
});

// ─── rowContextMenu is a deprecated alias ────────────────────────

it('keeps rowContextMenu working as an alias into the context menu', function () {
    $table = Table::make()->rowContextMenu([Action::make('archive')->label('Archive')]);

    expect($table->hasRowContextMenu())->toBeTrue()
        ->and($table->getContextMenuActions())->toHaveCount(1);
});

// ─── Keyboard navigation config (F5) ─────────────────────────────

it('picks the double-click action as the keyboard primary, click as secondary', function () {
    $table = Table::make()->recordActions([
        RecordAction::make('view')->onClick(),
        RecordAction::make('edit')->onDoubleClick(),
    ]);

    $config = $table->getRecordActionKeyboardConfig();

    expect($config['primary'])->toBe('edit')
        ->and($config['secondary'])->toBe('view');
});

it('has no secondary with a single pointer binding', function () {
    $config = Table::make()
        ->recordAction(RecordAction::make('edit')->onDoubleClick())
        ->getRecordActionKeyboardConfig();

    expect($config['primary'])->toBe('edit')
        ->and($config['secondary'])->toBeNull();
});

it('maps record-action keyboard shortcuts and reserves enter/space', function () {
    $table = Table::make()->recordActions([
        RecordAction::make(Action::make('remove'))->onKey('Delete'),
        RecordAction::make(Action::make('open'))->onKey('Enter'), // reserved → excluded
    ]);

    expect($table->getRecordActionKeyboardConfig()['shortcuts'])->toBe(['Delete' => 'remove']);
});

it('keeps a name-referenced onKey shortcut even with no action to stamp', function () {
    // 'remove' is declared without its own keyboardShortcut(); the binding only
    // references it and adds onKey('Delete'). The key lives on the trigger, so it
    // must still reach the client shortcut map.
    $table = Table::make()
        ->actions([Action::make('remove')])
        ->recordActions([RecordAction::make('remove')->onKey('Delete')]);

    expect($table->getRecordActionKeyboardConfig()['shortcuts'])->toBe(['Delete' => 'remove']);
});

it('enables keyboard nav automatically when record actions exist', function () {
    expect(Table::make()->keyboardNavEnabled())->toBeFalse()
        ->and(Table::make()->getTableRole())->toBeNull();

    $table = Table::make()->recordAction(RecordAction::make('edit')->onDoubleClick());

    expect($table->keyboardNavEnabled())->toBeTrue()
        ->and($table->getTableRole())->toBe('grid');
});

it('lets keyboard nav be forced off or on', function () {
    $off = Table::make()
        ->recordAction(RecordAction::make('edit')->onDoubleClick())
        ->recordActionKeyboard(false);
    expect($off->keyboardNavEnabled())->toBeFalse()
        ->and($off->getTableRole())->toBeNull();

    expect(Table::make()->recordActionKeyboard()->keyboardNavEnabled())->toBeTrue();
});

it('exposes the active-row class and selection flag in the keyboard config', function () {
    $config = Table::make()
        ->selectable()
        ->activeRowClass('bg-amber-100')
        ->recordAction(RecordAction::make('edit')->onDoubleClick())
        ->getRecordActionKeyboardConfig();

    expect($config['selectable'])->toBeTrue()
        ->and($config['activeClass'])->toBe('bg-amber-100');
});
