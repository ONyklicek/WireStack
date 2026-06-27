<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use NyonCode\WireTable\Concerns\HasResponsive;
use NyonCode\WireTable\Concerns\HasSqlDebug;
use Workbench\App\Models\Task;

/**
 * Coverage for standalone table concerns exercised outside a concrete Column:
 * HasResponsive (also mixed into Column) and HasSqlDebug.
 */
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
