<?php

declare(strict_types=1);

/**
 * A shipped migration never declares a NOT NULL timestamp without a default.
 *
 * MariaDB before 10.10, and MySQL with `explicit_defaults_for_timestamp` off,
 * give such a column a default of their own: the first one in a table gets
 * `DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`, every later one a
 * zero date that strict mode refuses. The one-time codes table shipped two of
 * them and failed to migrate on MariaDB 10.6 — and had it migrated, every
 * wrong guess would have moved the code's expiry to "now". CI runs MySQL 8 and
 * MariaDB 11, where the setting is on, so nothing there could see it.
 *
 * It reads the source rather than a schema for the same reason: the databases
 * the suite has cannot produce the failure. A column that is always written by
 * the package is a `dateTime`; one the database may fill says `nullable()`,
 * `useCurrent()` or `default()`.
 */
it('declares no NOT NULL timestamp without a default in a shipped migration', function (): void {
    $root = dirname(__DIR__, 2);
    $offenders = [];

    foreach (glob($root.'/packages/*/database/migrations/*.php') ?: [] as $file) {
        $source = (string) file_get_contents($file);

        // One Blueprint statement: `$table->timestamp('x')` up to its `;`,
        // chained calls on following lines included.
        preg_match_all('/\$\w+->timestamps?(?:Tz)?\(\s*[\'"](\w+)[\'"][^;]*;/', $source, $matches, PREG_SET_ORDER);

        foreach ($matches as [$statement, $column]) {
            if (preg_match('/->(nullable|useCurrent|default)\(/', $statement) !== 1) {
                $offenders[] = str_replace($root.'/', '', $file).": {$column}";
            }
        }
    }

    expect($offenders)->toBe([]);
});
