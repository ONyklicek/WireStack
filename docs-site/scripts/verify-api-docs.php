<?php

declare(strict_types=1);
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Icons\DefaultIconSet;

/*
 * API documentation gate — catches docs and code drifting apart in either
 * direction. Both failure modes have actually shipped here:
 *
 *   1. Undocumented API — DateTimePicker::asMonth() existed for months while the
 *      docs claimed the modes were only date/time/datetime.
 *   2. Documented fiction — SelectColumn::native() was advertised as switching to
 *      a "custom styled dropdown" while nothing read it.
 *
 * A page opts in by being a class's reference page, which the docs already signal
 * by convention: the H1 is the class short name and the class is imported.
 *
 *     # SelectFilter
 *     use NyonCode\WireTable\Filters\SelectFilter;
 *
 * That deliberately excludes guides (authorization.md, testing.md, …) which
 * import components only to show examples and are not anyone's API reference.
 *
 * Scope is deliberately narrow to stay signal-only:
 *   - only methods DECLARED on that class (inherited config lives in the shared
 *     "Common Field API" pages, not per-field);
 *   - only public fluent setters (returning static/self) — the configuration
 *     surface a user actually types. Getters and render internals are excluded.
 *
 * A method may be marked intentionally undocumented with @docs-ignore in its
 * docblock. Usage: php docs-site/scripts/verify-api-docs.php [repo-root]
 */

$repo = rtrim($argv[1] ?? getcwd(), '/');
require $repo.'/vendor/autoload.php';

$failures = [];

/**
 * Names provided by the class's traits, recursively.
 *
 * Needed because reflection reports a trait method's declaring class as the
 * class that uses it — so without this, every page would be asked to document
 * shared concerns (HasSheetOnMobile, HasNativeControl, …) that are documented
 * centrally instead.
 *
 * @return array<int, string>
 */
function traitMethods(ReflectionClass $class): array
{
    $names = [];

    foreach ($class->getTraits() as $trait) {
        foreach ($trait->getMethods() as $m) {
            $names[] = $m->getName();
        }
        $names = [...$names, ...traitMethods($trait)];
    }

    return $names;
}

/** Methods a doc page is expected to cover: public, declared here, fluent. */
function configApi(ReflectionClass $class): array
{
    $out = [];
    $fromTraits = traitMethods($class);

    foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
        if ($m->getDeclaringClass()->getName() !== $class->getName()) {
            continue; // inherited — documented by the shared API page
        }
        if (in_array($m->getName(), $fromTraits, true)) {
            continue; // a shared concern, documented centrally
        }
        if ($m->isStatic() || $m->isConstructor()) {
            continue;
        }
        if (str_contains((string) $m->getDocComment(), '@docs-ignore')) {
            continue;
        }

        $type = $m->getReturnType();
        $name = $type instanceof ReflectionNamedType ? $type->getName() : null;
        if ($name !== 'static' && $name !== 'self') {
            continue; // not a fluent setter
        }

        $out[] = $m->getName();
    }

    sort($out);

    return $out;
}

/**
 * Method names the page presents as THIS class's own API — i.e. inside its API
 * section, which the docs write either as "## X API" (a `->foo()` code block) or
 * "## Methods" (a table). Names in prose elsewhere are out of scope: those
 * legitimately call Eloquent, Carbon and friends.
 *
 * Two things must not be mistaken for the class's own API, both of which the
 * real docs do:
 *   - a table may carry an "On" column naming a *sibling* class (tabs.md
 *     documents Tab::icon() next to Tabs::activeTab()) — such a row belongs to
 *     that class, not this page's;
 *   - a code sample may mention someone else's method, including inside a
 *     comment (`->livewireAction('downloadPdf') // calls $this->downloadPdf()`).
 *     The API blocks list one `->method(` per line, so anchoring to the line
 *     start keeps mid-line calls out.
 *
 * @return array<int, string>
 */
function apiSectionMethods(string $markdown, string $shortName): array
{
    if (! preg_match('/^#{2,3}\s+.*\b(API|Methods|Metody)\b.*$/mi', $markdown, $m, PREG_OFFSET_CAPTURE)) {
        return [];
    }

    $body = substr($markdown, (int) $m[0][1] + strlen((string) $m[0][0]));
    // stop at the next heading of the same or higher level
    if (preg_match('/^#{1,3}\s+/m', $body, $next, PREG_OFFSET_CAPTURE)) {
        $body = substr($body, 0, (int) $next[0][1]);
    }

    // Code-block form: "->method(...)" starting its own line.
    preg_match_all('/^\s*->([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/m', $body, $arrow);

    // Table form: "| `method(...)` | On | Description |".
    $rows = [];
    foreach (explode("\n", $body) as $line) {
        if (! preg_match('/^\|\s*`(?:->)?([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $line, $cell)) {
            continue;
        }

        // Split on real cell separators only: a signature legitimately contains
        // an escaped pipe (`icon(string\|Icon)`), which is not a column break.
        $cells = array_map('trim', preg_split('/(?<!\\\\)\|/', trim($line, '| ')) ?: []);
        $owner = isset($cells[1]) ? trim($cells[1], '` ') : '';

        // An owner column naming another class hands the row to that class.
        if ($owner !== '' && $owner !== $shortName && preg_match('/^[A-Z][A-Za-z0-9_]*$/', $owner)) {
            continue;
        }

        $rows[] = $cell[1];
    }

    return array_values(array_unique([...$arrow[1], ...$rows]));
}

/** Every method name the page mentions, as `->foo(` or a `foo()` API-table row. */
function documentedMethods(string $markdown): array
{
    preg_match_all('/->([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $markdown, $arrow);
    preg_match_all('/`([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $markdown, $table);

    return array_unique([...$arrow[1], ...$table[1]]);
}

/**
 * State maps written the wrong way round.
 *
 * `->colors()` / `->icons()` are indexed by the STATE — `['active' => 'success']`.
 * Every example across the docs had it backwards (`['success' => 'active']`), which
 * no gate could see: the method exists and the argument is still an array of
 * strings, so reflection is happy while the reader gets a silently gray badge.
 *
 * Detected without a hard-coded word list: colour and icon names are closed
 * vocabularies, so a KEY that is one of them cannot be a state.
 *
 * @param  array<string, int>  $vocabulary  the closed set this map's VALUES draw from
 * @return array<int, string>
 */
function backwardsStateMaps(string $markdown, string $method, array $vocabulary): array
{
    $found = [];
    $lines = explode("\n", $markdown);

    foreach ($lines as $i => $line) {
        if (! str_contains($line, "->{$method}([")) {
            continue;
        }

        for ($j = $i + 1; $j < count($lines) && ! str_contains($lines[$j], ']'); $j++) {
            if (preg_match("/^\s*'([^']+)'\s*=>/", $lines[$j], $m) && isset($vocabulary[$m[1]])) {
                $found[] = ($j + 1).": '{$m[1]}'";
            }
        }
    }

    return $found;
}

$colorNames = array_flip(Color::values());
$iconNames = array_flip((new DefaultIconSet)->names());

$docs = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($repo.'/docs'));
$pages = [];
foreach ($docs as $f) {
    if ($f->isFile() && $f->getExtension() === 'md') {
        $pages[] = $f->getPathname();
    }
}
sort($pages);

$checked = 0;

foreach ($pages as $page) {
    $md = file_get_contents($page);
    $rel = substr($page, strlen($repo) + 1);

    // Runs on every page: guides carry these examples too, and they are not
    // anyone's class reference.
    foreach ([['colors', $colorNames], ['icons', $iconNames]] as [$method, $vocabulary]) {
        foreach (backwardsStateMaps($md, $method, $vocabulary) as $hit) {
            $failures[] = "$rel — ->{$method}() map is keyed by the value, not the state (line $hit)";
        }
    }

    if (! preg_match('/^#\s+(.+)$/m', $md, $h1)) {
        continue;
    }

    // The page is a class reference only when its H1 names an imported class.
    preg_match_all('/^use (NyonCode\\\\[A-Za-z0-9_\\\\]+);$/m', $md, $uses);
    $fqcn = null;
    foreach ($uses[1] as $candidate) {
        if (str_ends_with($candidate, '\\'.trim($h1[1]))) {
            $fqcn = $candidate;
            break;
        }
    }

    if ($fqcn === null) {
        continue; // a guide, not an API reference
    }

    if (! class_exists($fqcn)) {
        $failures[] = "$rel — documents a class that does not exist: $fqcn";

        continue;
    }

    $class = new ReflectionClass($fqcn);
    if ($class->isAbstract()) {
        continue;
    }

    $checked++;
    $documented = documentedMethods($md);

    // 1. code has it, docs do not
    foreach (configApi($class) as $method) {
        if (! in_array($method, $documented, true)) {
            $failures[] = "$rel — undocumented: {$class->getShortName()}::{$method}() is a public fluent setter but the page never mentions it";
        }
    }

    // 2. docs have it, code does not — judged only inside the page's API section
    //    ("## X API" listing `->foo()` in a code block, or "## Methods" with a
    //    table). Prose elsewhere legitimately calls other objects' methods.
    foreach (apiSectionMethods($md, $class->getShortName()) as $method) {
        if (! $class->hasMethod($method)) {
            $failures[] = "$rel — documents a method that does not exist: {$class->getShortName()}::{$method}()";
        }
    }
}

/*
 * Baseline: the gaps that already existed when this gate was added (mostly
 * TextInputColumn, which documents ~a third of its API). Recording them lets the
 * gate guard against NEW drift today instead of waiting for the backlog to be
 * written — while keeping the debt visible and countable rather than forgotten.
 *
 * Shrink it by documenting a method and re-running with --update-baseline.
 */
$baselineFile = $repo.'/docs-site/api-docs-baseline.txt';
sort($failures);

if (in_array('--update-baseline', $argv, true)) {
    file_put_contents($baselineFile, implode("\n", $failures)."\n");
    echo 'api-docs baseline updated — '.count($failures)." known gap(s) recorded.\n";
    exit(0);
}

$baseline = is_file($baselineFile)
    ? array_values(array_filter(array_map('trim', file($baselineFile))))
    : [];

$new = array_values(array_diff($failures, $baseline));
$fixed = array_values(array_diff($baseline, $failures));

if ($new !== []) {
    echo 'api-docs FAILED — '.count($new)." new issue(s):\n";
    foreach ($new as $f) {
        echo "  ✗ $f\n";
    }
    echo "\nFix the page, or — for an undocumented method that is deliberately internal — mark it @docs-ignore.\n";
    exit(1);
}

echo "api-docs OK — $checked class reference pages match their public fluent API";
if ($baseline !== []) {
    echo ' ('.count($baseline).' known gap(s) in the baseline';
    echo $fixed !== [] ? '; '.count($fixed).' now fixed — refresh with --update-baseline)' : ')';
}
echo ".\n";
