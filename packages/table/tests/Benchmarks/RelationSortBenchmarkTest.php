<?php

declare(strict_types=1);

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Relation-sort benchmark: LEFT JOIN ordering vs. correlated-subquery ordering.
 *
 * This decides step 3 of the Eloquent-native consolidation: is ordering a
 * paginated query by a belongsTo column cheaper as a JOIN or as a correlated
 * subquery? SQLite is not representative — run it against the real engines:
 *
 *   DB_CONNECTION=mysql  DB_DATABASE=wire_bench DB_USERNAME=... BENCH=1 \
 *     vendor/bin/pest packages/table/tests/Benchmarks/RelationSortBenchmarkTest.php
 *   DB_CONNECTION=pgsql  DB_DATABASE=wire_bench DB_USERNAME=... BENCH=1 \
 *     vendor/bin/pest packages/table/tests/Benchmarks/RelationSortBenchmarkTest.php
 *
 * Without BENCH=1 the file registers nothing, so the normal suite skips it.
 */

if (! env('BENCH')) {
    return;
}

/**
 * Seed a users→companies dataset. `indexSortColumn` controls whether the ordered
 * column (companies.name) is indexed, which is the decisive factor for subquery
 * ordering cost.
 */
function benchSeed(int $companies, int $users, bool $indexSortColumn): void
{
    Schema::dropIfExists('bench_users');
    Schema::dropIfExists('bench_companies');

    Schema::create('bench_companies', function (Blueprint $t) use ($indexSortColumn) {
        $t->id();
        $t->string('name');
        if ($indexSortColumn) {
            $t->index('name');
        }
    });

    Schema::create('bench_users', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->unsignedBigInteger('company_id')->nullable()->index();
    });

    $batch = [];
    for ($i = 1; $i <= $companies; $i++) {
        // Reverse-ish naming so name order differs from id order (forces real sort work).
        $batch[] = ['name' => 'Co-'.str_pad((string) ($companies - $i), 7, '0', STR_PAD_LEFT)];
        if (count($batch) >= 2000) {
            DB::table('bench_companies')->insert($batch);
            $batch = [];
        }
    }
    if ($batch !== []) {
        DB::table('bench_companies')->insert($batch);
    }

    $batch = [];
    for ($i = 1; $i <= $users; $i++) {
        $batch[] = ['name' => 'User-'.$i, 'company_id' => (($i - 1) % $companies) + 1];
        if (count($batch) >= 2000) {
            DB::table('bench_users')->insert($batch);
            $batch = [];
        }
    }
    if ($batch !== []) {
        DB::table('bench_users')->insert($batch);
    }

    // Refresh planner statistics so both strategies are compared on fresh stats.
    $driver = DB::connection()->getDriverName();
    if ($driver === 'pgsql') {
        DB::statement('ANALYZE bench_users');
        DB::statement('ANALYZE bench_companies');
    } elseif ($driver === 'mysql') {
        DB::statement('ANALYZE TABLE bench_users');
        DB::statement('ANALYZE TABLE bench_companies');
    }
}

/** ORDER BY companies.name via a LEFT JOIN (users.id tiebreaker for determinism). */
function benchJoinSort(int $offset): QueryBuilder
{
    return DB::table('bench_users')
        ->select('bench_users.*')
        ->leftJoin('bench_companies', 'bench_users.company_id', '=', 'bench_companies.id')
        ->orderBy('bench_companies.name')
        ->orderBy('bench_users.id')
        ->offset($offset)
        ->limit(25);
}

/** ORDER BY a correlated subquery selecting companies.name (users.id tiebreaker). */
function benchSubquerySort(int $offset): QueryBuilder
{
    return DB::table('bench_users')
        ->orderBy(
            DB::table('bench_companies')
                ->select('name')
                ->whereColumn('bench_companies.id', 'bench_users.company_id')
                ->limit(1)
        )
        ->orderBy('bench_users.id')
        ->offset($offset)
        ->limit(25);
}

/**
 * Median + min wall-clock (ms) over `$iters` runs of `$build()->get()`, warmed once.
 *
 * @param  callable(): QueryBuilder  $build
 * @return array{median: float, min: float}
 */
function benchTime(callable $build, int $iters = 15): array
{
    $build()->get();

    $times = [];
    for ($i = 0; $i < $iters; $i++) {
        $start = hrtime(true);
        $build()->get();
        $times[] = (hrtime(true) - $start) / 1e6;
    }
    sort($times);

    return ['median' => $times[intdiv(count($times), 2)], 'min' => $times[0]];
}

function benchReport(string $title, int $offset): void
{
    $join = benchTime(fn () => benchJoinSort($offset));
    $sub = benchTime(fn () => benchSubquerySort($offset));
    $ratio = $sub['median'] / max($join['median'], 0.0001);

    printf(
        "\n[%s] offset=%d\n  JOIN     median=%.2fms min=%.2fms\n  SUBQUERY median=%.2fms min=%.2fms  (%.2fx JOIN)\n",
        $title, $offset, $join['median'], $join['min'], $sub['median'], $sub['min'], $ratio,
    );
}

function benchExplain(string $label, QueryBuilder $q): void
{
    printf("  EXPLAIN %s: %s\n", $label, json_encode($q->explain()->all()));
}

it('benchmarks relation sort: JOIN vs correlated subquery', function () {
    $driver = DB::connection()->getDriverName();
    $companies = (int) env('BENCH_COMPANIES', 2000);
    $users = (int) env('BENCH_USERS', 50000);
    $deep = (int) ($users * 0.5);

    printf("\n=== Relation sort benchmark (driver=%s, companies=%d, users=%d) ===\n", $driver, $companies, $users);

    foreach ([false, true] as $indexed) {
        benchSeed($companies, $users, $indexed);
        $label = $indexed ? 'sort column INDEXED' : 'sort column NOT indexed';

        printf("\n--- %s ---", $label);
        benchReport($label, 0);
        benchReport($label, $deep);

        benchExplain('JOIN    ', benchJoinSort(0));
        benchExplain('SUBQUERY', benchSubquerySort(0));

        // Sanity: both strategies return the same ordered ids.
        $joinIds = benchJoinSort(0)->pluck('id')->all();
        $subIds = benchSubquerySort(0)->pluck('id')->all();
        expect($subIds)->toBe($joinIds);
    }

    Schema::dropIfExists('bench_users');
    Schema::dropIfExists('bench_companies');
})->skip(fn () => ! env('BENCH'), 'set BENCH=1 to run the DB benchmark');
