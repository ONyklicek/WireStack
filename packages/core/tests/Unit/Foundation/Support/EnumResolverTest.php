<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasColor;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasIcon;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Foundation\Support\EnumResolver;

// ─── Test enums ──────────────────────────────────────────────────────────────

enum ErBackedStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

enum ErUnitStatus
{
    case Low;
    case High;
}

enum ErRichStatus: string implements HasColor, HasIcon, HasLabel
{
    case Open = 'open';
    case Closed = 'closed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Open => 'Otevřeno',
            self::Closed => 'Zavřeno',
        };
    }

    public function getColor(): string|Color|null
    {
        return match ($this) {
            self::Open => Color::Success,
            self::Closed => Color::Danger,
        };
    }

    public function getIcon(): string|Icon|null
    {
        return match ($this) {
            self::Open => Icon::check,
            self::Closed => Icon::xMark,
        };
    }
}

// ─── scalar() ────────────────────────────────────────────────────────────────

it('reduces a backed enum to its backing value', function () {
    expect(EnumResolver::scalar(ErBackedStatus::Active))->toBe('active');
});

it('reduces a unit enum to its case name', function () {
    expect(EnumResolver::scalar(ErUnitStatus::High))->toBe('High');
});

it('passes scalar and non-enum values through unchanged', function () {
    expect(EnumResolver::scalar('plain'))->toBe('plain')
        ->and(EnumResolver::scalar(42))->toBe(42)
        ->and(EnumResolver::scalar(null))->toBeNull()
        ->and(EnumResolver::scalar(['a' => 1]))->toBe(['a' => 1]);
});

// ─── label() ─────────────────────────────────────────────────────────────────

it('uses getLabel() for enums implementing HasLabel', function () {
    expect(EnumResolver::label(ErRichStatus::Open))->toBe('Otevřeno');
});

it('falls back to scalar for enums without HasLabel', function () {
    expect(EnumResolver::label(ErBackedStatus::Inactive))->toBe('inactive')
        ->and(EnumResolver::label(ErUnitStatus::Low))->toBe('Low')
        ->and(EnumResolver::label('plain'))->toBe('plain');
});

// ─── display() ───────────────────────────────────────────────────────────────

it('collapses array/JSON values to a compact JSON string', function () {
    expect(EnumResolver::display(['a' => 1, 'b' => 2]))->toBe('{"a":1,"b":2}')
        ->and(EnumResolver::display(['x', 'y']))->toBe('["x","y"]');
});

it('display falls back to label/scalar for non-array values', function () {
    expect(EnumResolver::display(ErRichStatus::Open))->toBe('Otevřeno')
        ->and(EnumResolver::display(ErBackedStatus::Active))->toBe('active')
        ->and(EnumResolver::display('plain'))->toBe('plain')
        ->and(EnumResolver::display(null))->toBeNull();
});

// ─── color() / icon() ────────────────────────────────────────────────────────

it('resolves color and icon from enum contracts', function () {
    expect(EnumResolver::color(ErRichStatus::Open))->toBe(Color::Success)
        ->and(EnumResolver::icon(ErRichStatus::Closed))->toBe(Icon::xMark);
});

it('returns null color/icon for enums and values without the contracts', function () {
    expect(EnumResolver::color(ErBackedStatus::Active))->toBeNull()
        ->and(EnumResolver::icon(ErBackedStatus::Active))->toBeNull()
        ->and(EnumResolver::color('plain'))->toBeNull()
        ->and(EnumResolver::icon(null))->toBeNull();
});

// ─── isEnum() ────────────────────────────────────────────────────────────────

it('detects enum instances', function () {
    expect(EnumResolver::isEnum(ErBackedStatus::Active))->toBeTrue()
        ->and(EnumResolver::isEnum(ErUnitStatus::Low))->toBeTrue()
        ->and(EnumResolver::isEnum('active'))->toBeFalse()
        ->and(EnumResolver::isEnum(null))->toBeFalse();
});
