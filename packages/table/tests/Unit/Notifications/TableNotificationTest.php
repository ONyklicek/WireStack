<?php

declare(strict_types=1);

use NyonCode\WireCore\Notifications\Notification;

// ─── Factory ────────────────────────────────────────────────────────────────

it('can be created via make()', function () {
    $notification = Notification::make('success', 'Test');

    expect($notification->type)->toBe('success')
        ->and($notification->message)->toBe('Test');
});

// ─── Shortcuts ──────────────────────────────────────────────────────────────

it('has success shortcut', function () {
    $n = Notification::success('Uloženo');

    expect($n->type)->toBe('success')
        ->and($n->message)->toBe('Uloženo');
});

it('has error shortcut', function () {
    $n = Notification::error('Chyba');

    expect($n->type)->toBe('error')
        ->and($n->message)->toBe('Chyba');
});

it('has warning shortcut', function () {
    $n = Notification::warning('Pozor');

    expect($n->type)->toBe('warning')
        ->and($n->message)->toBe('Pozor');
});

it('has info shortcut', function () {
    $n = Notification::info('Info');

    expect($n->type)->toBe('info')
        ->and($n->message)->toBe('Info');
});

// ─── Immutability ───────────────────────────────────────────────────────────

it('is immutable - fluent methods return new instances', function () {
    $original = Notification::success('Test');
    $modified = $original->title('Title');

    expect($original->title)->toBeNull()
        ->and($modified->title)->toBe('Title')
        ->and($original)->not->toBe($modified);
});

it('can set title', function () {
    $n = Notification::success('Test')->title('Hotovo');

    expect($n->title)->toBe('Hotovo');
});

it('can set duration', function () {
    $n = Notification::success('Test')->duration(5000);

    expect($n->duration)->toBe(5000);
});

it('can set icon', function () {
    $n = Notification::success('Test')->icon('check');

    expect($n->icon)->toBe('check');
});

it('can set position', function () {
    $n = Notification::success('Test')->position('top-right');

    expect($n->position)->toBe('top-right');
});

it('can set extra data', function () {
    $n = Notification::success('Test')->extra(['key' => 'value']);

    expect($n->extra)->toBe(['key' => 'value']);
});

it('merges extra data', function () {
    $n = Notification::success('Test')
        ->extra(['a' => 1])
        ->extra(['b' => 2]);

    expect($n->extra)->toBe(['a' => 1, 'b' => 2]);
});

// ─── Serialization ──────────────────────────────────────────────────────────

it('can serialize to array', function () {
    $n = Notification::success('Uloženo')
        ->title('Hotovo')
        ->duration(3000);

    $array = $n->toArray();

    expect($array)->toHaveKey('type', 'success')
        ->and($array)->toHaveKey('message', 'Uloženo')
        ->and($array)->toHaveKey('title', 'Hotovo')
        ->and($array)->toHaveKey('duration', 3000);
});

it('filters null values from array', function () {
    $n = Notification::success('Test');

    $array = $n->toArray();

    expect($array)->not->toHaveKey('title')
        ->and($array)->not->toHaveKey('duration')
        ->and($array)->not->toHaveKey('icon')
        ->and($array)->not->toHaveKey('position')
        ->and($array)->not->toHaveKey('extra');
});
