<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use NyonCode\WireTable\Concerns\HasFormatting;
use NyonCode\WireTable\Concerns\HasResponsive;
use NyonCode\WireTable\Concerns\HasSqlDebug;
use NyonCode\WireTable\Concerns\WithRowPolling;
use Workbench\App\Models\Task;

/**
 * Coverage for the standalone/legacy table concerns that are not wired into a
 * concrete Column but are still part of the public surface.
 */
function formattingDouble(): object
{
    return new class
    {
        use HasFormatting;
    };
}

function responsiveDouble(): object
{
    return new class
    {
        use HasResponsive;
    };
}

function sqlDebugDouble(): object
{
    return new class
    {
        use HasSqlDebug;

        public static function publicInterpolate(string $sql, array $bindings): string
        {
            return self::interpolateSql($sql, $bindings);
        }

        public static function publicBuilderToSql(Builder $query): string
        {
            return self::builderToSql($query);
        }
    };
}

// ─── HasFormatting ─────────────────────────────────────────────

test('formatMoney renders known currencies and a fallback', function () {
    $d = formattingDouble();

    expect($d::formatMoney(1234.5))->toBe('1 234,50 Kč')
        ->and($d::formatMoney(1000, 'EUR'))->toBe('1 000,00 €')
        ->and($d::formatMoney(1000, 'USD'))->toBe('$1 000,00')
        ->and($d::formatMoney(1000, 'GBP'))->toBe('£1 000,00')
        ->and($d::formatMoney(1000, 'PLN'))->toBe('1 000,00 PLN')
        ->and($d::formatMoney(null))->toBe('')
        ->and($d::formatMoney(''))->toBe('');
});

test('formatNumber formats with custom separators', function () {
    $d = formattingDouble();

    expect($d::formatNumber(1234.567, 2))->toBe('1 234,57')
        ->and($d::formatNumber(1234, 0, '.', ','))->toBe('1,234')
        ->and($d::formatNumber(null))->toBe('');
});

test('formatDate, formatDateTime and formatSince handle values and invalid input', function () {
    $d = formattingDouble();

    expect($d::formatDate('2026-01-02'))->toBe('02.01.2026')
        ->and($d::formatDateTime('2026-01-02 13:45'))->toBe('02.01.2026 13:45')
        ->and($d::formatDate(null))->toBe('')
        ->and($d::formatDate('not-a-date'))->toBe('not-a-date')
        ->and($d::formatSince(null))->toBe('')
        ->and($d::formatSince('also-not-a-date'))->toBe('also-not-a-date')
        ->and($d::formatSince('2020-01-01'))->toContain('ago');
});

// ─── HasResponsive ─────────────────────────────────────────────

test('responsive classes default to empty', function () {
    expect(responsiveDouble()->getResponsiveClasses())->toBe('');
});

test('visibleFrom produces hidden + breakpoint table-cell classes', function () {
    foreach (['sm', 'md', 'lg', 'xl', '2xl'] as $bp) {
        expect(responsiveDouble()->visibleFrom($bp)->getResponsiveClasses())
            ->toBe("hidden {$bp}:table-cell");
    }

    expect(responsiveDouble()->visibleFrom('unknown')->getResponsiveClasses())
        ->toBe('hidden md:table-cell');
});

test('hiddenFrom produces breakpoint hidden classes', function () {
    foreach (['sm', 'md', 'lg', 'xl'] as $bp) {
        expect(responsiveDouble()->hiddenFrom($bp)->getResponsiveClasses())
            ->toBe("{$bp}:hidden");
    }

    expect(responsiveDouble()->hiddenFrom('unknown')->getResponsiveClasses())
        ->toBe('md:hidden');
});

test('visibleFrom and hiddenFrom can combine', function () {
    expect(responsiveDouble()->visibleFrom('md')->hiddenFrom('xl')->getResponsiveClasses())
        ->toBe('hidden md:table-cell xl:hidden');
});

// ─── HasSqlDebug ───────────────────────────────────────────────

test('interpolateSql replaces bindings safely by type', function () {
    $d = sqlDebugDouble();

    $sql = 'select * from t where a = ? and b = ? and c = ? and d = ? and e = ?';
    $out = $d::publicInterpolate($sql, [null, true, false, 42, "O'Brien"]);

    expect($out)->toBe(
        "select * from t where a = NULL and b = 1 and c = 0 and d = 42 and e = 'O\\'Brien'"
    );
});

test('interpolateSql leaves placeholders untouched when bindings run out', function () {
    $d = sqlDebugDouble();

    // More placeholders than bindings: the extra '?' is left in place.
    expect($d::publicInterpolate('a = ? and b = ?', [1]))
        ->toBe('a = 1 and b = ?');

    // More bindings than placeholders: the loop hits the $pos === false branch.
    expect($d::publicInterpolate('a = ?', [1, 2, 3]))
        ->toBe('a = 1');
});

test('interpolateSql does not re-match a question mark inside a substituted value', function () {
    $d = sqlDebugDouble();

    // The '?' inside the first value must not consume the second placeholder.
    expect($d::publicInterpolate('select ? , ?', ['why?', 'because']))
        ->toBe("select 'why?' , 'because'");
});

test('builderToSql interpolates an eloquent builder', function () {
    $d = sqlDebugDouble();

    $sql = $d::publicBuilderToSql(Task::query()->where('id', 7));

    expect($sql)->toContain('"id" = 7');
});

// ─── WithRowPolling ────────────────────────────────────────────

test('refreshRow is a safe no-op by default', function () {
    $component = new class extends Component
    {
        use WithRowPolling;

        public function render()
        {
            return '<div></div>';
        }
    };

    expect($component->refreshRow(1))->toBeNull();
});
