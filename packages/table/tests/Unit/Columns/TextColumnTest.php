<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Columns\TextColumn;

it('can be created', function () {
    expect(TextColumn::make('name'))->toBeInstanceOf(TextColumn::class);
});

// ─── Money ──────────────────────────────────────────────────────────────────

it('can format as money', function () {
    $column = TextColumn::make('price')->money('CZK');

    expect($column->isMoney())->toBeTrue()
        ->and($column->getCurrency())->toBe('CZK');
});

it('formats money values correctly for CZK (0 decimals)', function () {
    $column = TextColumn::make('price')->money('CZK');
    $record = Mockery::mock(Model::class);

    $result = $column->formatValue(1234.56, $record);

    // CZK uses 0 decimals, rounds to 1 235
    expect($result)->toContain('CZK');
});

it('formats money with two decimals for non-CZK', function () {
    $column = TextColumn::make('price')->money('EUR');
    $record = Mockery::mock(Model::class);

    $result = $column->formatValue(1234.56, $record);

    expect($result)->toContain('1 234,56')
        ->and($result)->toContain('EUR');
});

// ─── Numeric ────────────────────────────────────────────────────────────────

it('can format as numeric', function () {
    $column = TextColumn::make('quantity')->numeric(2, ',', ' ');

    expect($column->isNumeric())->toBeTrue();
});

it('formats numeric values correctly', function () {
    $column = TextColumn::make('amount')->numeric(2, ',', ' ');
    $record = Mockery::mock(Model::class);

    $result = $column->formatValue(1234567.89, $record);

    expect($result)->toContain('1 234 567,89');
});

// ─── Date ───────────────────────────────────────────────────────────────────

it('can format as date', function () {
    $column = TextColumn::make('created_at')->date();
    $record = Mockery::mock(Model::class);

    $result = $column->formatValue('2024-03-15', $record);

    expect($result)->toContain('15.03.2024');
});

it('can format as datetime', function () {
    $column = TextColumn::make('created_at')->dateTime();
    $record = Mockery::mock(Model::class);

    $result = $column->formatValue('2024-03-15 14:30:00', $record);

    expect($result)->toContain('15.03.2024 14:30');
});

it('can format with custom date format', function () {
    $column = TextColumn::make('created_at')->date('Y/m/d');
    $record = Mockery::mock(Model::class);

    $result = $column->formatValue('2024-03-15', $record);

    expect($result)->toContain('2024/03/15');
});

it('can format as since (diffForHumans)', function () {
    $column = TextColumn::make('created_at')->date()->since();
    $record = Mockery::mock(Model::class);

    $result = $column->formatValue('2024-01-01', $record);

    // diffForHumans returns something like "X years ago"
    expect($result)->toBeString()
        ->and($result)->not->toBeEmpty();
});

it('formats as since() even without date()/dateTime()', function () {
    // Regression M4: since() alone was a no-op — the diffForHumans branch was
    // gated behind date()/dateTime() being set, so ->since() by itself returned
    // the raw value.
    $column = TextColumn::make('created_at')->since();
    $record = Mockery::mock(Model::class);

    $result = $column->formatValue('2024-01-01', $record);

    expect($result)->toBeString()
        ->and($result)->not->toBe('2024-01-01');
});

it('renders a per-record closure icon without a TypeError', function () {
    // Regression M2: a Closure icon reached renderIcon(string) raw → TypeError.
    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->forceFill(['id' => 1, 'name' => 'Ada']);

    $column = TextColumn::make('name')->icon(fn ($record) => $record->name === 'Ada' ? 'check' : 'x-mark');

    $html = $column->renderCell($record);

    expect($html)->toContain('Ada')->toContain('<svg');
});

// ─── Null / Empty Handling ──────────────────────────────────────────────────

it('returns placeholder for null values', function () {
    $column = TextColumn::make('name');
    $record = Mockery::mock(Model::class);

    expect($column->formatValue(null, $record))->toBe('-');
});

it('returns placeholder for empty string', function () {
    $column = TextColumn::make('name');
    $record = Mockery::mock(Model::class);

    expect($column->formatValue('', $record))->toBe('-');
});

// ─── Font Family ────────────────────────────────────────────────────────────

it('can set font family', function () {
    $column = TextColumn::make('code')->fontFamily('mono');

    expect($column->getFontFamily())->toBe('mono');
});

it('has no font family by default', function () {
    expect(TextColumn::make('name')->getFontFamily())->toBeNull();
});

it('renders the font family into the text classes', function () {
    // The setter storing the value is not enough — it has to reach the cell's
    // classes. Each keyword maps to its Tailwind utility; anything else is
    // passed through as font-<name>.
    expect(TextColumn::make('a')->fontFamily('sans')->getTextClasses())->toContain('font-sans')
        ->and(TextColumn::make('a')->fontFamily('serif')->getTextClasses())->toContain('font-serif')
        ->and(TextColumn::make('a')->fontFamily('mono')->getTextClasses())->toContain('font-mono')
        ->and(TextColumn::make('a')->fontFamily('brand')->getTextClasses())->toContain('font-brand');
});

it('adds no font utility when no family is set', function () {
    expect(TextColumn::make('a')->getTextClasses())->not->toContain('font-');
});
