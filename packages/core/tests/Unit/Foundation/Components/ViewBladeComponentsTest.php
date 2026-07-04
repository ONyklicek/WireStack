<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\View\BulkButtonComponent;
use NyonCode\WireCore\Actions\View\ButtonComponent;
use NyonCode\WireCore\Actions\View\GroupComponent;
use NyonCode\WireCore\Foundation\View\Badge;
use NyonCode\WireCore\Foundation\View\Button;
use NyonCode\WireCore\Foundation\View\Dropdown;
use NyonCode\WireCore\Foundation\View\WidgetGrid;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;

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
        // Sheet-on-mobile is on by default; consumers can opt out.
        ->and($dropdown->sheetOnMobile)->toBeTrue()
        ->and((new Dropdown(sheetOnMobile: false))->sheetOnMobile)->toBeFalse()
        ->and($dropdown->render()->name())->toBe('wire-core::foundation.dropdown');
});

it('widget grid keeps its widgets + column count and renders its view', function () {
    $widgets = [StatsOverviewWidget::make()];
    $grid = new WidgetGrid(widgets: $widgets, columns: 3);

    expect($grid->widgets)->toBe($widgets)
        ->and($grid->columns)->toBe(3)
        ->and((new WidgetGrid)->columns)->toBe(2)
        ->and($grid->render()->name())->toBe('wire-core::widgets.widget-grid');
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
