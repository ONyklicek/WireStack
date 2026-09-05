<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Plugin\Contracts\IdentifiesHookTarget;
use NyonCode\WireCore\Core\Plugin\HookTarget;

/*
 * Which component a hook payload came from — the answer that used to be a
 * duck-typed guess in every callback that wanted to touch one table rather than
 * all of them.
 */

class HtInvoice
{
    public string $number = 'INV-1';
}

class HtOverdueInvoice extends HtInvoice {}

class HtPlainPage
{
    public string $name = 'plain';
}

class HtResourcePage implements IdentifiesHookTarget
{
    public function hookKey(): ?string
    {
        return 'invoices';
    }
}

class HtUnregisteredPage implements IdentifiesHookTarget
{
    public function hookKey(): ?string
    {
        return null;
    }
}

it('asks the host which registry entry it shows', function () {
    $target = HookTarget::for('table', new HtResourcePage, HtInvoice::class);

    expect($target->key)->toBe('invoices')
        ->and($target->surface)->toBe('table')
        ->and($target->model)->toBe(HtInvoice::class);
});

it('leaves the key null for a host that declares none', function () {
    // A standalone table in a hand-written component belongs to no registry
    // entry. That is ordinary, not a gap — it is still addressable by class.
    expect(HookTarget::for('table', new HtPlainPage)->key)->toBeNull()
        ->and(HookTarget::for('table', new HtUnregisteredPage)->key)->toBeNull();
});

it('takes a model as an instance or as a class name', function () {
    // A table knows the class it queries; a form holds the record it edits.
    expect(HookTarget::for('form', null, new HtInvoice)->model)->toBe(HtInvoice::class)
        ->and(HookTarget::for('table', null, HtInvoice::class)->model)->toBe(HtInvoice::class);
});

it('ignores a host that is not an object', function () {
    // Table::getLivewireComponent() is `mixed` and answers null outside Livewire.
    $target = HookTarget::for('table', 'not-a-component');

    expect($target->host)->toBeNull();
});

it('matches a scope naming the registered key', function () {
    expect(HookTarget::for('table', new HtResourcePage)->matches('invoices'))->toBeTrue()
        ->and(HookTarget::for('table', new HtResourcePage)->matches('tasks'))->toBeFalse();
});

it('matches a scope naming the model, including a subclass of it', function () {
    // A package scoping to the model it ships still reaches an application that
    // extended that model, which is the common way one is customised.
    $target = HookTarget::for('form', null, HtOverdueInvoice::class);

    expect($target->matches(HtOverdueInvoice::class))->toBeTrue()
        ->and($target->matches(HtInvoice::class))->toBeTrue()
        ->and($target->matches(HtPlainPage::class))->toBeFalse();
});

it('matches a scope naming the host, or a class the host extends', function () {
    // How a package scopes a callback to every page it ships.
    $target = HookTarget::for('table', new HtResourcePage);

    expect($target->matches(HtResourcePage::class))->toBeTrue()
        ->and($target->matches(IdentifiesHookTarget::class))->toBeTrue()
        ->and($target->matches(HtPlainPage::class))->toBeFalse();
});

it('matches nothing at all for an empty scope', function () {
    expect(HookTarget::for('table', new HtResourcePage)->matches(''))->toBeFalse();
});

it('matches nothing when it knows nothing', function () {
    $target = new HookTarget('table');

    expect($target->matches('invoices'))->toBeFalse()
        ->and($target->key)->toBeNull()
        ->and($target->host)->toBeNull()
        ->and($target->model)->toBeNull();
});
