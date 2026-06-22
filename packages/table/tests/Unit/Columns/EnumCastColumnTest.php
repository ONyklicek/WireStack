<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasColor;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasIcon;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Columns\IconColumn;
use NyonCode\WireTable\Columns\TextColumn;

// ─── Test enums ──────────────────────────────────────────────────────────────

enum EccBackedStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

enum EccUnitStatus
{
    case Low;
    case High;
}

enum EccRichStatus: string implements HasColor, HasIcon, HasLabel
{
    case Open = 'open';
    case Closed = 'closed';

    public function getLabel(): ?string
    {
        return $this === self::Open ? 'Otevřeno' : 'Zavřeno';
    }

    public function getColor(): string|Color|null
    {
        return $this === self::Open ? Color::Success : Color::Danger;
    }

    public function getIcon(): string|Icon|null
    {
        return $this === self::Open ? Icon::check : Icon::xMark;
    }
}

// ─── Test model ──────────────────────────────────────────────────────────────

class EccTicket extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status' => EccBackedStatus::class,
        'priority' => EccUnitStatus::class,
        'phase' => EccRichStatus::class,
        'meta' => 'array',
    ];
}

function eccRecord(array $attributes): EccTicket
{
    return (new EccTicket)->forceFill($attributes);
}

// ─── TextColumn ──────────────────────────────────────────────────────────────

it('renders a plain column over a backed enum cast without fataling', function () {
    $record = eccRecord(['status' => 'active']);

    expect(Column::make('status')->getState($record))->toBe(EccBackedStatus::Active)
        ->and(Column::make('status')->renderCell($record))->toContain('active');
});

it('renders a unit enum cast using the case name', function () {
    $record = eccRecord(['priority' => 'High']);

    expect(TextColumn::make('priority')->renderCell($record))->toContain('High');
});

it('renders a HasLabel enum cast using its label', function () {
    $record = eccRecord(['phase' => 'open']);

    expect(TextColumn::make('phase')->renderCell($record))->toContain('Otevřeno');
});

it('formats an array/JSON cast as compact JSON instead of "Array"', function () {
    $record = eccRecord(['meta' => ['k' => 'v', 'n' => 3]]);

    expect(TextColumn::make('meta')->formatValue($record->meta, $record))->toBe('{"k":"v","n":3}');
});

it('does not render the literal "Array" for a JSON cast cell', function () {
    $record = eccRecord(['meta' => ['k' => 'v']]);

    expect(TextColumn::make('meta')->renderCell($record))->not->toContain('Array');
});

// ─── BadgeColumn ─────────────────────────────────────────────────────────────

it('matches a badge color map by the enum scalar value', function () {
    $column = BadgeColumn::make('status')->colors([
        'active' => 'success',
        'inactive' => 'danger',
    ]);

    expect($column->getColorForState(EccBackedStatus::Active))->toBe('success')
        ->and($column->getColorForState(EccBackedStatus::Inactive))->toBe('danger');
});

it('auto-resolves badge color and icon from enum contracts', function () {
    $column = BadgeColumn::make('phase');

    expect($column->getColorForState(EccRichStatus::Open))->toBe('success')
        ->and($column->getColorForState(EccRichStatus::Closed))->toBe('danger')
        ->and($column->getIconForState(EccRichStatus::Open))->toBe('check')
        ->and($column->getIconForState(EccRichStatus::Closed))->toBe('x-mark');
});

it('renders a badge cell with the enum label', function () {
    $record = eccRecord(['phase' => 'closed']);

    expect(BadgeColumn::make('phase')->renderCell($record))->toContain('Zavřeno');
});

// ─── IconColumn ──────────────────────────────────────────────────────────────

it('auto-resolves icon column glyph and color from enum contracts', function () {
    $column = IconColumn::make('phase');

    expect($column->getIconForState(EccRichStatus::Open))->toBe('check')
        ->and($column->getColorForState(EccRichStatus::Closed))->toBe('danger');
});
