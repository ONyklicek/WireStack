<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\View\BulkButtonComponent;
use NyonCode\WireCore\Actions\View\ButtonComponent;
use NyonCode\WireCore\Actions\View\GroupComponent;
use NyonCode\WireCore\Foundation\View\Badge;
use NyonCode\WireCore\Foundation\View\Button;
use NyonCode\WireCore\Foundation\View\Dropdown;

// ─── Foundation Blade components ───────────────────────────────

it('badge resolves color classes and renders its view', function () {
    $badge = new Badge(color: 'success', icon: 'check', size: 'md');

    expect($badge->getColor())->toBe('success')
        ->and($badge->colorClasses)->toBeString()->not->toBeEmpty()
        ->and($badge->render()->name())->toBe('wire-core::foundation.badge');
});

it('button keeps its props and renders its view', function () {
    $button = new Button(color: 'primary', size: 'lg', outlined: true, icon: 'plus', href: '/go');

    expect($button->color)->toBe('primary')
        ->and($button->outlined)->toBeTrue()
        ->and($button->href)->toBe('/go')
        ->and($button->render()->name())->toBe('wire-core::foundation.button');
});

it('dropdown keeps its props and renders its view', function () {
    $dropdown = new Dropdown(position: 'top-start', width: 'w-64', trigger: 'click');

    expect($dropdown->position)->toBe('top-start')
        ->and($dropdown->width)->toBe('w-64')
        ->and($dropdown->render()->name())->toBe('wire-core::foundation.dropdown');
});

// ─── Action Blade components (render returns a view name string) ─

it('action button component carries action context and view name', function () {
    $action = (object) ['name' => 'edit'];
    $component = new ButtonComponent(action: $action, record: ['id' => 1], wireClick: 'do()');

    expect($component->action)->toBe($action)
        ->and($component->wireClick)->toBe('do()')
        ->and($component->render())->toBe('wire-core::actions.button');
});

it('bulk button component carries action context and view name', function () {
    $action = (object) ['name' => 'delete'];
    $component = new BulkButtonComponent(action: $action, wireClick: 'bulk()');

    expect($component->action)->toBe($action)
        ->and($component->wireClick)->toBe('bulk()')
        ->and($component->render())->toBe('wire-core::actions.bulk-button');
});

it('group component carries group context and view name', function () {
    $group = (object) ['name' => 'more'];
    $component = new GroupComponent(group: $group, record: ['id' => 2]);

    expect($component->group)->toBe($group)
        ->and($component->record)->toBe(['id' => 2])
        ->and($component->render())->toBe('wire-core::actions.group');
});
