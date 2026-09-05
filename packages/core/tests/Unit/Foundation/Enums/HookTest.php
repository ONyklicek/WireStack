<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Enums\Hook;

it('resolves a name from either form it can arrive in', function () {
    // The whole point of the enum: `Hook::TableConfiguring` and the string it
    // stands for are the same name, so a 2.x plugin and a new one register
    // against the same list.
    expect(Hook::name(Hook::TableConfiguring))->toBe('table.configuring')
        ->and(Hook::name('table.configuring'))->toBe('table.configuring');
});

it('passes a name it does not know through unchanged', function () {
    // A package dispatching its own lifecycle point does not need a case here.
    expect(Hook::name('acme.invoice.approving'))->toBe('acme.invoice.approving');
});

it('lists every shipped hook name', function () {
    expect(Hook::values())->toContain(
        'table.configuring',
        'table.querying',
        'table.queried',
        'form.configuring',
        'form.saving',
        'form.saved',
        'action.executing',
        'action.executed',
    );
});
