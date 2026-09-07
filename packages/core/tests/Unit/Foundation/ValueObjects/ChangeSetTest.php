<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\ValueObjects\ChangeSet;

/*
 * What a stored value looks like once it is a change.
 *
 * A small rule that was written twice and had already drifted: the audit trail
 * drew it in a Blade ternary and printed a boolean `true` as `1`; the audit
 * module's screen had its own `display()` with the boolean case in it. The
 * disagreement is the reason this exists — not the code it saves.
 */

it('pairs each moved field with what it was and what it became', function () {
    $set = ChangeSet::fromDiff([
        'status' => ['old' => 'draft', 'new' => 'sent'],
        'total' => ['old' => 100, 'new' => 250],
    ]);

    expect($set->rows)->toBe([
        ['field' => 'status', 'before' => 'draft', 'after' => 'sent'],
        ['field' => 'total', 'before' => '100', 'after' => '250'],
    ])->and($set->fields())->toBe(['status', 'total'])
        ->and($set->isEmpty())->toBeFalse();
});

it('keeps null as null so a renderer can say "empty"', function () {
    // The two most readable rows in a diff are "was nothing, now set" and "was
    // set, now nothing". Both need the renderer to tell absence from an empty
    // string, so this must not helpfully cast to ''.
    $set = ChangeSet::fromDiff([
        'note' => ['old' => null, 'new' => 'hello'],
        'cancelled_at' => ['old' => '2026-01-01', 'new' => null],
    ]);

    expect($set->rows[0]['before'])->toBeNull()
        ->and($set->rows[0]['after'])->toBe('hello')
        ->and($set->rows[1]['before'])->toBe('2026-01-01')
        ->and($set->rows[1]['after'])->toBeNull();
});

it('writes a boolean as true and false, not as 1 and nothing', function () {
    // The drift, in one assertion: `(string) true` is `1` and `(string) false`
    // is `''` — the second of which renders as the placeholder that means "there
    // was no value", which is a different fact.
    $set = ChangeSet::fromDiff(['paid' => ['old' => false, 'new' => true]]);

    expect($set->rows[0]['before'])->toBe('false')
        ->and($set->rows[0]['after'])->toBe('true');
});

it('writes an array as JSON, readable rather than escaped', function () {
    // A JSON column, or a bulk action's list of ids. Unescaped slashes and
    // unicode, because the point is that somebody reads it.
    $set = ChangeSet::fromDiff([
        'tags' => ['old' => ['a'], 'new' => ['a', 'b']],
        'path' => ['old' => null, 'new' => ['url' => 'https://x/y', 'name' => 'Přílohy']],
    ]);

    expect($set->rows[0]['after'])->toBe('["a","b"]')
        ->and($set->rows[1]['after'])->toBe('{"url":"https://x/y","name":"Přílohy"}');
});

it('survives a pair that is missing a side', function () {
    // A hand-built diff, or one from an older row. A missing key is "there was
    // nothing", which is exactly what null already means here.
    $set = ChangeSet::fromDiff(['status' => ['new' => 'sent']]);

    expect($set->rows[0])->toBe(['field' => 'status', 'before' => null, 'after' => 'sent']);
});

it('has an empty answer that is a ChangeSet, not a null', function () {
    // So a caller with nothing to show still has something to ask isEmpty() of.
    expect(ChangeSet::empty()->isEmpty())->toBeTrue()
        ->and(ChangeSet::empty()->rows)->toBe([])
        ->and(ChangeSet::empty()->fields())->toBe([]);
});

it('numbers its rows from zero whatever the field names are', function () {
    // The rows are a list, not a map: a renderer loops them, and a diff keyed by
    // field name would put the field in two places.
    $set = ChangeSet::fromDiff(['z' => ['old' => 1, 'new' => 2], 'a' => ['old' => 3, 'new' => 4]]);

    expect(array_keys($set->rows))->toBe([0, 1])
        ->and($set->fields())->toBe(['z', 'a']);
});
