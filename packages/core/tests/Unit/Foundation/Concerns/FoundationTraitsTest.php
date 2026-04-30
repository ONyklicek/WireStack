<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Concerns;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;

// Test helper: create a class that uses Foundation traits
function makeFoundationField(string $name = 'test_field'): object
{
    return new class($name)
    {
        use Concerns\BelongsToComponent;
        use Concerns\CanBeLive;
        use Concerns\CanBeReadOnly;
        use Concerns\HasColumnSpan;
        use Concerns\HasDebounce;
        use Concerns\HasDefault;
        use Concerns\HasExtraAttributes;
        use Concerns\HasHelperText;
        use Concerns\HasHint;
        use Concerns\HasIcon;
        use Concerns\HasId;
        use Concerns\HasLabel;
        use Concerns\HasName;
        use Concerns\HasPlaceholder;
        use Concerns\HasPrefixAndSuffix;
        use Concerns\HasSize;
        use Concerns\HasState;
        use Concerns\HasTooltip;
        use Concerns\HasVisibility;
        use EvaluatesClosures;

        public function __construct(string $name)
        {
            $this->name = $name;
        }
    };
}

// ─── HasName ────────────────────────────────────────────────

it('has a name', function () {
    $field = makeFoundationField('email');
    expect($field->getName())->toBe('email');
});

// ─── HasLabel ───────────────────────────────────────────────

it('auto-generates label from name', function () {
    $field = makeFoundationField('first_name');
    expect($field->getLabel())->toBe('First Name');
});

it('accepts custom label', function () {
    $field = makeFoundationField('name');
    $field->label('Jméno');
    expect($field->getLabel())->toBe('Jméno');
});

it('accepts closure label', function () {
    $field = makeFoundationField('name');
    $field->label(fn () => 'Dynamic');
    expect($field->getLabel())->toBe('Dynamic');
});

// ─── HasId ──────────────────────────────────────────────────

it('uses state path as default id', function () {
    $field = makeFoundationField('name');
    expect($field->getId())->toBe('name');
});

it('accepts custom id', function () {
    $field = makeFoundationField('name');
    $field->id('custom-id');
    expect($field->getId())->toBe('custom-id');
});

// ─── HasIcon ────────────────────────────────────────────────

it('has no icon by default', function () {
    $field = makeFoundationField('name');
    expect($field->getIcon())->toBeNull();
});

it('accepts icon', function () {
    $field = makeFoundationField('name');
    $field->icon('pencil');
    expect($field->getIcon())->toBe('pencil');
});

it('accepts icon position', function () {
    $field = makeFoundationField('name');
    $field->icon('pencil', 'after');
    expect($field->getIconPosition())->toBe('after');
});

// ─── HasState ───────────────────────────────────────────────

it('returns name as state path without prefix', function () {
    $field = makeFoundationField('email');
    expect($field->getStatePath())->toBe('email');
});

it('returns prefixed state path', function () {
    $field = makeFoundationField('email');
    $field->statePath('data');
    expect($field->getStatePath())->toBe('data.email');
});

it('returns wire model attribute', function () {
    $field = makeFoundationField('name');
    $field->statePath('form');
    expect($field->getWireModelAttribute())->toBe('form.name');
});

// ─── HasDefault ─────────────────────────────────────────────

it('has null default by default', function () {
    $field = makeFoundationField('name');
    expect($field->getDefault())->toBeNull();
});

it('accepts static default', function () {
    $field = makeFoundationField('role');
    $field->default('user');
    expect($field->getDefault())->toBe('user');
});

it('accepts closure default', function () {
    $field = makeFoundationField('role');
    $field->default(fn () => 'admin');
    expect($field->getDefault())->toBe('admin');
});

// ─── HasVisibility ──────────────────────────────────────────

it('is visible by default', function () {
    $field = makeFoundationField('name');
    expect($field->isVisible())->toBeTrue()
        ->and($field->isHidden())->toBeFalse()
        ->and($field->isDisabled())->toBeFalse();
});

it('can be hidden', function () {
    $field = makeFoundationField('name');
    $field->hidden();
    expect($field->isHidden())->toBeTrue()
        ->and($field->isVisible())->toBeFalse();
});

it('can be hidden with closure', function () {
    $field = makeFoundationField('name');
    $field->hidden(fn () => true);
    expect($field->isHidden())->toBeTrue();
});

it('can be disabled', function () {
    $field = makeFoundationField('name');
    $field->disabled();
    expect($field->isDisabled())->toBeTrue();
});

// ─── HasHelperText ──────────────────────────────────────────

it('has no helper text by default', function () {
    $field = makeFoundationField('name');
    expect($field->getHelperText())->toBeNull();
});

it('accepts helper text', function () {
    $field = makeFoundationField('name');
    $field->helperText('Enter your full name');
    expect($field->getHelperText())->toBe('Enter your full name');
});

// ─── HasHint ────────────────────────────────────────────────

it('has no hint by default', function () {
    $field = makeFoundationField('name');
    expect($field->getHint())->toBeNull();
});

it('accepts hint with icon and color', function () {
    $field = makeFoundationField('name');
    $field->hint('Required')->hintIcon('info')->hintColor('primary');
    expect($field->getHint())->toBe('Required')
        ->and($field->getHintIcon())->toBe('info')
        ->and($field->getHintColor())->toBe('primary');
});

// ─── HasPlaceholder ─────────────────────────────────────────

it('accepts placeholder', function () {
    $field = makeFoundationField('email');
    $field->placeholder('you@example.com');
    expect($field->getPlaceholder())->toBe('you@example.com');
});

// ─── HasSize ────────────────────────────────────────────────

it('has md size by default', function () {
    $field = makeFoundationField('name');
    expect($field->getSize())->toBe('md');
});

it('accepts size shortcuts', function () {
    $field = makeFoundationField('name');
    $field->sm();
    expect($field->getSize())->toBe('sm');

    $field->lg();
    expect($field->getSize())->toBe('lg');
});

// ─── HasPrefixAndSuffix ─────────────────────────────────────

it('accepts prefix and suffix', function () {
    $field = makeFoundationField('price');
    $field->prefix('$')->suffix('.00');
    expect($field->getPrefix())->toBe('$')
        ->and($field->getSuffix())->toBe('.00')
        ->and($field->hasAffix())->toBeTrue();
});

it('accepts prefix and suffix icons', function () {
    $field = makeFoundationField('search');
    $field->prefixIcon('filter')->suffixIcon('x');
    expect($field->getPrefixIcon())->toBe('filter')
        ->and($field->getSuffixIcon())->toBe('x');
});

// ─── HasColumnSpan ──────────────────────────────────────────

it('has no column span by default', function () {
    $field = makeFoundationField('name');
    expect($field->getColumnSpan())->toBeNull();
});

it('accepts column span', function () {
    $field = makeFoundationField('name');
    $field->columnSpan(2);
    expect($field->getColumnSpan())->toBe(2);
});

it('accepts full column span', function () {
    $field = makeFoundationField('name');
    $field->columnSpanFull();
    expect($field->getColumnSpan())->toBe('full');
});

// ─── CanBeReadOnly ──────────────────────────────────────────

it('is not read-only by default', function () {
    $field = makeFoundationField('name');
    expect($field->isReadOnly())->toBeFalse();
});

it('can be set to read-only', function () {
    $field = makeFoundationField('name');
    $field->readOnly();
    expect($field->isReadOnly())->toBeTrue();
});

// ─── CanBeLive ──────────────────────────────────────────────

it('is not live by default', function () {
    $field = makeFoundationField('name');
    expect($field->isLive())->toBeFalse()
        ->and($field->getWireModelModifier())->toBe('');
});

it('can be live', function () {
    $field = makeFoundationField('name');
    $field->live();
    expect($field->isLive())->toBeTrue()
        ->and($field->getWireModelModifier())->toBe('live');
});

it('can be live on blur', function () {
    $field = makeFoundationField('name');
    $field->liveOnBlur();
    expect($field->isLiveOnBlur())->toBeTrue()
        ->and($field->getWireModelModifier())->toBe('blur');
});

// ─── HasDebounce ────────────────────────────────────────────

it('has no debounce by default', function () {
    $field = makeFoundationField('name');
    expect($field->getDebounce())->toBeNull()
        ->and($field->getDebounceModifier())->toBe('');
});

it('accepts debounce', function () {
    $field = makeFoundationField('name');
    $field->debounce(300);
    expect($field->getDebounce())->toBe(300)
        ->and($field->getDebounceModifier())->toBe('.debounce.300ms');
});

// ─── HasExtraAttributes ─────────────────────────────────────

it('has empty extra attributes by default', function () {
    $field = makeFoundationField('name');
    expect($field->getExtraAttributes())->toBe([]);
});

it('accepts extra attributes', function () {
    $field = makeFoundationField('name');
    $field->extraAttributes(['data-testid' => 'name-field', 'x-ref' => 'nameInput']);
    expect($field->getExtraAttributes())
        ->toBe(['data-testid' => 'name-field', 'x-ref' => 'nameInput']);
});

// ─── BelongsToComponent ─────────────────────────────────────

it('has no livewire component by default', function () {
    $field = makeFoundationField('name');
    expect($field->getLivewire())->toBeNull();
});

it('can be assigned to a livewire component', function () {
    $field = makeFoundationField('name');
    $fakeComponent = new stdClass;
    $field->livewire($fakeComponent);
    expect($field->getLivewire())->toBe($fakeComponent);
});

// ─── HasTooltip ─────────────────────────────────────────────

it('has no tooltip by default', function () {
    $field = makeFoundationField('name');
    expect($field->getTooltip())->toBeNull();
});

it('accepts tooltip', function () {
    $field = makeFoundationField('name');
    $field->tooltip('Enter your full legal name');
    expect($field->getTooltip())->toBe('Enter your full legal name');
});
