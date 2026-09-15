<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Setup;

/**
 * What a migration leaves in the schema: the tables it creates, the columns it adds.
 *
 * Read off the source rather than by running it, because the question is asked
 * of a migration that must *not* run — one an installer has just published into
 * an application that already has its tables, from an earlier copy that was
 * pruned into a schema dump, another package, or a migration of its own.
 *
 * ## It reads literals, and nothing else
 *
 * `Schema::create('passkeys', …)` and `$table->text('two_factor_secret')` inside
 * `Schema::table('users', …)` are the shapes read, and only inside `up()`. A
 * table named from config (`Schema::create($tableNames['roles'], …)`) is not a
 * name this can know, so it is simply absent — and an empty footprint is never
 * "already there". A caller that knows such a migration names its footprint
 * itself ({@see RedundantMigrations::around()}'s `$known`).
 *
 * Index, key and drop calls are not columns, and `morphs('x')` names no column
 * it creates: a column this reads wrongly can only make a migration look *not*
 * done, which leaves it to run and fail loudly rather than be dropped silently.
 */
final readonly class MigrationFootprint
{
    /** Blueprint calls whose first argument is not a column the call creates. */
    private const NOT_COLUMNS = [
        'index', 'unique', 'primary', 'foreign', 'fulltext', 'spatialindex', 'rawindex',
        'morphs', 'nullablemorphs', 'uuidmorphs', 'nullableuuidmorphs', 'ulidmorphs', 'nullableulidmorphs',
        'numericmorphs', 'nullablenumericmorphs', 'comment', 'after', 'change', 'engine', 'charset', 'collation',
    ];

    /**
     * @param  array<int, string>  $creates  Tables the migration creates.
     * @param  array<string, array<int, string>>  $adds  Columns it adds to a table it does not create.
     * @param  array<string, array<int, string>>  $columns  Every column it defines, created table or not.
     */
    public function __construct(
        public array $creates = [],
        public array $adds = [],
        public array $columns = [],
    ) {}

    /**
     * The footprint of a migration file's `up()`.
     */
    public static function read(string $source): self
    {
        $up = self::up($source);

        if (preg_match_all('/Schema::(create|table)\(\s*([\'"])([^\'"]+)\2/', $up, $calls, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === 0) {
            return new self;
        }

        $creates = [];
        $adds = [];
        $columns = [];

        foreach ($calls as $index => $call) {
            $table = $call[3][0];
            $start = $call[0][1];
            $end = $calls[$index + 1][0][1] ?? strlen($up);
            $defined = self::columnsIn(substr($up, $start, $end - $start));

            $columns[$table] = [...($columns[$table] ?? []), ...$defined];

            if ($call[1][0] === 'create') {
                $creates[] = $table;
            } elseif ($defined !== []) {
                $adds[$table] = [...($adds[$table] ?? []), ...$defined];
            }
        }

        return new self(
            array_values(array_unique($creates)),
            array_map(static fn (array $c): array => array_values(array_unique($c)), $adds),
            array_map(static fn (array $c): array => array_values(array_unique($c)), $columns),
        );
    }

    public function isEmpty(): bool
    {
        return $this->creates === [] && $this->adds === [];
    }

    /**
     * Whether everything this footprint makes is also made by the others together.
     *
     * @param  array<int, self>  $others
     */
    public function coveredBy(array $others): bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        $tables = array_merge([], ...array_map(static fn (self $o): array => $o->creates, $others));
        $columns = [];

        foreach ($others as $other) {
            foreach ($other->columns as $table => $names) {
                $columns[$table] = [...($columns[$table] ?? []), ...$names];
            }
        }

        foreach ($this->creates as $table) {
            if (! in_array($table, $tables, true)) {
                return false;
            }
        }

        foreach ($this->adds as $table => $names) {
            if (array_diff($names, $columns[$table] ?? []) !== []) {
                return false;
            }
        }

        return true;
    }

    /**
     * The body of `up()`: from its declaration to `down()`'s, or to the end.
     */
    private static function up(string $source): string
    {
        $from = strpos($source, 'function up');

        if ($from === false) {
            return '';
        }

        $to = strpos($source, 'function down', $from);

        return $to === false ? substr($source, $from) : substr($source, $from, $to - $from);
    }

    /**
     * @return array<int, string>
     */
    private static function columnsIn(string $call): array
    {
        preg_match_all('/\$(?!this\b)\w+->(\w+)\(\s*([\'"])([^\'"]+)\2/', $call, $matches, PREG_SET_ORDER);

        $columns = [];

        foreach ($matches as $match) {
            $method = strtolower($match[1]);

            if (in_array($method, self::NOT_COLUMNS, true) || str_starts_with($method, 'drop') || str_starts_with($method, 'rename')) {
                continue;
            }

            $columns[] = $match[3];
        }

        return $columns;
    }
}
