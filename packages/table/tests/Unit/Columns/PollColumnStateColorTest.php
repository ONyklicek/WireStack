<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasColor as HasColorContract;
use NyonCode\WireTable\Columns\PollColumn;

/*
 * PollColumn resolved its own colors and fell out of the enum-cast consolidation,
 * so each of these threw a TypeError out of a public method — the first one
 * straight through renderCell(). They are regression tests: the resolution now
 * belongs to InteractsWithStateColor, and these pin PollColumn to it.
 */

enum PcsStatus: string implements HasColorContract
{
    case Queued = 'queued';
    case Done = 'done';

    public function getColor(): string|Color|null
    {
        return match ($this) {
            self::Queued => 'warning',
            self::Done => Color::Success,
        };
    }
}

test('a colorUsing callback may return a Color enum', function () {
    expect(PollColumn::make('status')->colorUsing(fn () => Color::Success)->getColorForState('queued'))
        ->toBe('success');
});

test('a colors map may be keyed for an enum state and hold Color enums', function () {
    $column = PollColumn::make('status')->colors(['queued' => Color::Warning]);

    expect($column->getColorForState(PcsStatus::Queued))->toBe('warning');
});

test('an enum state carrying the HasColor contract resolves without a map', function () {
    $column = PollColumn::make('status');

    expect($column->getColorForState(PcsStatus::Queued))->toBe('warning')
        ->and($column->getColorForState(PcsStatus::Done))->toBe('success');
});

test('an unmapped state falls back to the column color, then to gray', function () {
    expect(PollColumn::make('status')->getColorForState('unknown'))->toBe('gray')
        ->and(PollColumn::make('status')->color('info')->getColorForState('unknown'))->toBe('info');
});

// The fatal itself: a Color-returning callback threw out of renderCell(), so the
// cell never rendered. Drive the real Blade path, not just the resolver.
class PcsJob extends Model
{
    protected $guarded = [];

    protected $casts = ['status' => PcsStatus::class];
}

test('a badge cell renders the callback color through the real view path', function () {
    $record = (new PcsJob)->forceFill(['status' => 'done']);

    $html = PollColumn::make('status')
        ->badge()
        ->colorUsing(fn () => Color::Success)
        ->renderCell($record);

    expect($html)->toContain(HasColor::getBadgeColorClasses(Color::Success->value));
});

test('a badge cell renders the color an enum state carries', function () {
    $record = (new PcsJob)->forceFill(['status' => 'queued']);

    expect(PollColumn::make('status')->badge()->renderCell($record))
        ->toContain(HasColor::getBadgeColorClasses(Color::Warning->value));
});
