<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Components\Component;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;

// Concrete test implementations
function makeConcreteComponent(string $name): Component
{
    return new class($name) extends Component
    {
        protected function viewName(): string
        {
            return 'test::component';
        }
    };
}

function makeConcreteLayout(?string $name = null): LayoutComponent
{
    return new class($name) extends LayoutComponent
    {
        protected function viewName(): string
        {
            return 'test::layout';
        }
    };
}

// ─���─ Component ──────────────────────────────────────────────

it('can be created with make()', function () {
    $component = makeConcreteComponent('email');

    expect($component->getName())->toBe('email')
        ->and($component->getLabel())->toBe('Email')
        ->and($component->getStatePath())->toBe('email');
});

it('supports fluent API chaining', function () {
    $component = makeConcreteComponent('name');

    $result = $component
        ->label('Full Name')
        ->helperText('Enter your name')
        ->default('Jane')
        ->size('lg')
        ->columnSpan(2);

    expect($result)->toBe($component)
        ->and($component->getLabel())->toBe('Full Name')
        ->and($component->getHelperText())->toBe('Enter your name')
        ->and($component->getDefault())->toBe('Jane')
        ->and($component->getSize())->toBe('lg')
        ->and($component->getColumnSpan())->toBe(2);
});

it('inherits state path from parent', function () {
    $component = makeConcreteComponent('name');
    $component->statePath('form.data');

    expect($component->getStatePath())->toBe('form.data.name');
});

// ─── LayoutComponent ────────────────────────────────────────

it('creates layout with schema', function () {
    $child1 = makeConcreteComponent('first_name');
    $child2 = makeConcreteComponent('last_name');

    $layout = makeConcreteLayout('info');
    $layout->schema([$child1, $child2]);

    expect($layout->getSchema())->toHaveCount(2)
        ->and($layout->getName())->toBe('info');
});

it('propagates state path to children', function () {
    $child = makeConcreteComponent('name');

    $layout = makeConcreteLayout();
    $layout->statePath('profile');
    $layout->schema([$child]);
    $layout->prepareChildren('data');

    expect($child->getStatePath())->toBe('data.profile.name');
});

it('cascades disabled state to children and nested layouts', function () {
    $child = makeConcreteComponent('name');

    $inner = makeConcreteLayout();
    $inner->schema([$child]);

    $outer = makeConcreteLayout();
    $outer->schema([$inner]);

    $outer->prepareChildren('data', false, null, true);

    expect($outer->isDisabled())->toBeTrue()
        ->and($inner->isDisabled())->toBeTrue()
        ->and($child->isDisabled())->toBeTrue();
});

it('does not disable children when the form is not disabled', function () {
    $child = makeConcreteComponent('name');
    $layout = makeConcreteLayout();
    $layout->schema([$child]);

    $layout->prepareChildren('data');

    expect($layout->isDisabled())->toBeFalse()
        ->and($child->isDisabled())->toBeFalse();
});

it('propagates state path to nested layouts', function () {
    $child = makeConcreteComponent('city');

    $innerLayout = makeConcreteLayout();
    $innerLayout->statePath('address');
    $innerLayout->schema([$child]);

    $outerLayout = makeConcreteLayout();
    $outerLayout->statePath('user');
    $outerLayout->schema([$innerLayout]);

    $outerLayout->prepareChildren('data');

    expect($child->getStatePath())->toBe('data.user.address.city');
});

it('layout is visible by default', function () {
    $layout = makeConcreteLayout();

    expect($layout->isVisible())->toBeTrue()
        ->and($layout->isHidden())->toBeFalse();
});

it('layout can be hidden', function () {
    $layout = makeConcreteLayout();
    $layout->hidden();

    expect($layout->isHidden())->toBeTrue()
        ->and($layout->isVisible())->toBeFalse();
});
