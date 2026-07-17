<?php

declare(strict_types=1);

/*
 * Coverage gate — two checks over one Clover report.
 *
 * 1. DIFF COVERAGE. Every line this change adds or edits must be covered.
 *    This is the real rule ("everything is tested"), applied where it can
 *    actually be met: to the code being written now. A whole-file rule would
 *    block a one-line fix to PollColumn until someone covered the other 86
 *    lines, so the diff — not the file — is the unit.
 *
 * 2. PACKAGE FLOORS. No package may drop below its recorded coverage. The repo
 *    carries ~1762 uncovered lines of history; `--min=100` would fail on day one
 *    and be switched off within a week. Floors stop the debt growing while the
 *    diff gate stops it being added to, and each floor is raised as the debt is
 *    paid down (`--update-floors`).
 *
 * Floors are integers on purpose: they leave headroom for the small differences
 * between coverage drivers (CI runs pcov, local runs xdebug) without letting a
 * real regression through.
 *
 * Usage:
 *   php scripts/verify-coverage.php build/clover.xml
 *   php scripts/verify-coverage.php build/clover.xml --diff=origin/1.x
 *   php scripts/verify-coverage.php build/clover.xml --update-floors
 */

$args = array_slice($argv, 1);
$clover = null;
$diffBase = null;
$updateFloors = false;

foreach ($args as $arg) {
    if ($arg === '--update-floors') {
        $updateFloors = true;
    } elseif (str_starts_with($arg, '--diff=')) {
        $diffBase = substr($arg, 7);
    } elseif (! str_starts_with($arg, '--')) {
        $clover = $arg;
    }
}

$repo = rtrim((string) shell_exec('git rev-parse --show-toplevel 2>/dev/null'));
$repo = realpath($repo !== '' ? $repo : getcwd()) ?: getcwd();
$floorsFile = $repo.'/scripts/coverage-floors.json';

if ($clover === null || ! is_file($clover)) {
    fwrite(STDERR, 'coverage FAILED — no Clover report at ['.($clover ?? '(none given)')."].\n");
    fwrite(STDERR, "Generate one: vendor/bin/pest --coverage-clover=build/clover.xml\n");
    exit(1);
}

/**
 * Per-file statement coverage, keyed by repo-relative path.
 *
 * @return array<string, array{covered: int, total: int, uncovered: array<int, true>}>
 */
function parseClover(string $path, string $repo): array
{
    $xml = simplexml_load_file($path);

    if ($xml === false) {
        fwrite(STDERR, "coverage FAILED — [{$path}] is not readable XML.\n");
        exit(1);
    }

    $files = [];

    foreach ($xml->xpath('//file') ?: [] as $file) {
        // realpath both sides: the report records the path the test run saw,
        // which may reach the repo through a symlink (/tmp -> /private/tmp) and
        // so not share a literal prefix with the repo root.
        $name = realpath((string) $file['name']) ?: (string) $file['name'];
        $relative = str_starts_with($name, $repo.'/') ? substr($name, strlen($repo) + 1) : $name;

        // Only the packages' shipped source is gated; tests and workbench are not.
        if (preg_match('#^packages/[^/]+/src/#', $relative) !== 1) {
            continue;
        }

        $covered = 0;
        $total = 0;
        $uncovered = [];

        foreach ($file->line as $line) {
            // "method" lines duplicate their signature line; counting statements
            // only is what pest's own percentage reports.
            if ((string) $line['type'] !== 'stmt') {
                continue;
            }

            $total++;

            if ((int) $line['count'] > 0) {
                $covered++;
            } else {
                $uncovered[(int) $line['num']] = true;
            }
        }

        $files[$relative] = ['covered' => $covered, 'total' => $total, 'uncovered' => $uncovered];
    }

    return $files;
}

/**
 * Added/edited line numbers per file, from a zero-context diff.
 *
 * @return array<string, array<int, true>>
 */
function changedLines(string $base): array
{
    $escaped = escapeshellarg($base);
    $diff = shell_exec("git diff --unified=0 {$escaped}...HEAD -- 'packages/*/src/*.php' 2>/dev/null");

    if (! is_string($diff) || trim($diff) === '') {
        return [];
    }

    $changed = [];
    $file = null;

    foreach (preg_split('/\R/', $diff) ?: [] as $line) {
        // A deleted file reads "+++ /dev/null". Clearing the current file (rather
        // than letting the previous one persist) keeps its hunks from being
        // attributed to whichever file came before it in the diff.
        if (str_starts_with($line, '+++ ')) {
            $file = preg_match('#^\+\+\+ b/(.+)$#', $line, $m) === 1 ? $m[1] : null;

            continue;
        }

        // @@ -old,n +new,n @@ — the +side is what exists after the change.
        if ($file !== null && preg_match('/^@@ -\S+ \+(\d+)(?:,(\d+))? @@/', $line, $m) === 1) {
            $start = (int) $m[1];
            $count = isset($m[2]) ? (int) $m[2] : 1;

            for ($i = 0; $i < $count; $i++) {
                $changed[$file][$start + $i] = true;
            }
        }
    }

    return $changed;
}

/**
 * @param  array<string, array{covered: int, total: int, uncovered: array<int, true>}>  $files
 * @return array<string, array{covered: int, total: int}>
 */
function byPackage(array $files): array
{
    $packages = [];

    foreach ($files as $relative => $data) {
        preg_match('#^packages/([^/]+)/src/#', $relative, $m);
        $package = $m[1];

        $packages[$package]['covered'] = ($packages[$package]['covered'] ?? 0) + $data['covered'];
        $packages[$package]['total'] = ($packages[$package]['total'] ?? 0) + $data['total'];
    }

    ksort($packages);

    return $packages;
}

$files = parseClover($clover, $repo);

if ($files === []) {
    fwrite(STDERR, "coverage FAILED — the report covers no packages/*/src files.\n");
    exit(1);
}

$packages = byPackage($files);
$actual = [];

foreach ($packages as $package => $data) {
    $actual[$package] = $data['total'] > 0 ? $data['covered'] / $data['total'] * 100 : 100.0;
}

if ($updateFloors) {
    /** @var array<string, int> $existing */
    $existing = is_file($floorsFile)
        ? (array) json_decode((string) file_get_contents($floorsFile), true)
        : [];

    $floors = [];
    echo "coverage floors updated:\n";

    foreach ($actual as $package => $pct) {
        $earned = (int) floor($pct);
        $previous = $existing[$package] ?? 0;

        // A ratchet: a floor only ever rises. If coverage has slipped below a
        // recorded floor, keep the floor and let the check report the shortfall
        // — lowering it here would quietly bless the regression it exists to
        // catch. (Coverage can slip for reasons outside this change, e.g. new
        // uncovered code extracted elsewhere on the branch.)
        $floors[$package] = max($earned, $previous);

        $note = match (true) {
            $earned > $previous => "raised to {$floors[$package]}%",
            $earned < $previous => "kept at {$previous}% (currently only ".sprintf('%.1f', $pct).'% — held to catch the regression)',
            default => "unchanged at {$floors[$package]}%",
        };

        printf("  %-10s %s\n", $package, $note);
    }

    file_put_contents($floorsFile, json_encode($floors, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

    exit(0);
}

/** @var array<string, int> $floors */
$floors = is_file($floorsFile)
    ? (array) json_decode((string) file_get_contents($floorsFile), true)
    : [];

$failures = [];
$raisable = [];

echo "Coverage by package:\n";

foreach ($actual as $package => $pct) {
    $floor = $floors[$package] ?? null;
    $note = '';

    if ($floor === null) {
        $note = '(no floor recorded — run --update-floors)';
    } elseif ($pct + 0.0001 < $floor) {
        $note = "FELL BELOW its {$floor}% floor";
        $failures[] = sprintf('%s: %.1f%% is under its %d%% floor.', $package, $pct, $floor);
    } elseif ((int) floor($pct) > $floor) {
        $note = "floor {$floor}% can be raised to ".(int) floor($pct).'%';
        $raisable[] = $package;
    } else {
        $note = "floor {$floor}%";
    }

    printf("  %-10s %6.1f%%  %s\n", $package, $pct, $note);
}

if ($diffBase !== null && $diffBase !== '') {
    $changed = changedLines($diffBase);
    $uncoveredChanges = [];

    foreach ($changed as $file => $lines) {
        if (! isset($files[$file])) {
            // A new file the report never saw is dead weight or untested; either
            // way it is not this check's call — the floors will catch it.
            continue;
        }

        $missed = array_keys(array_intersect_key($files[$file]['uncovered'], $lines));

        if ($missed !== []) {
            $uncoveredChanges[$file] = $missed;
        }
    }

    $changedFiles = count($changed);

    if ($uncoveredChanges === []) {
        echo "\nDiff coverage vs {$diffBase}: every changed line in {$changedFiles} source file(s) is covered.\n";
    } else {
        echo "\nDiff coverage vs {$diffBase} — these lines were added or edited but never run by a test:\n";

        foreach ($uncoveredChanges as $file => $lines) {
            $shown = array_slice($lines, 0, 12);
            $suffix = count($lines) > 12 ? ' … (+'.(count($lines) - 12).' more)' : '';
            echo "  {$file}:".implode(',', $shown).$suffix."\n";
        }

        $failures[] = 'new or edited code is not covered by a test.';
    }
}

if ($failures !== []) {
    fwrite(STDERR, "\ncoverage FAILED:\n");

    foreach ($failures as $failure) {
        fwrite(STDERR, "  ✗ {$failure}\n");
    }

    fwrite(STDERR, "\nCover the lines you changed, or say why they cannot be reached.\n");
    exit(1);
}

echo "\ncoverage OK";
echo $raisable !== []
    ? ' — '.implode(', ', $raisable).' improved; raise the floor with: composer coverage:floors'."\n"
    : ".\n";
