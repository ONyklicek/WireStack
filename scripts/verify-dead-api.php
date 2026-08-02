<?php

declare(strict_types=1);

/*
 * Dead-API gate — finds a public fluent setter whose value nothing ever reads.
 *
 * Seven of these shipped: SelectColumn::native(), ImageColumn::stacked() /
 * stackLimit() / visibility(), SelectColumn::relationship(), and
 * fileAttachmentsDirectory() on both editors. Each was fluent, plausible and
 * inert — ->native(false) simply did nothing, and the docs cheerfully described
 * the behaviour it did not have. Neither the test suite (setters set, getters
 * get) nor the api-docs gate (the method was mentioned, so not "undocumented")
 * could see it.
 *
 * The check traces the *property*, not the getter, because a value can be
 * consumed in ways a getter-name grep would miss:
 *   - straight from Blade (`$column->isStacked()`),
 *   - through a dynamic call (`method_exists($column, 'formatForSave')`),
 *   - or by the class's own render method reading `$this->x` directly.
 *
 * A setter is reported only when the property it writes is read by nobody, or
 * when every method that reads it is itself unreachable from production code.
 *
 * Mark a deliberate exception with @dead-api-ignore and a reason.
 * Usage: php scripts/verify-dead-api.php [repo-root]
 */

$repo = rtrim($argv[1] ?? getcwd(), '/');
require $repo.'/vendor/autoload.php';

/** Production code — what a real app runs. Tests and workbench demos do not count. */
function productionSources(string $repo): array
{
    $out = [];
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($repo.'/packages'));

    foreach ($rii as $f) {
        if (! $f->isFile()) {
            continue;
        }
        $path = $f->getPathname();
        if (str_contains($path, '/tests/') || str_contains($path, '/vendor/') || str_contains($path, '/node_modules/')) {
            continue;
        }
        if (preg_match('/\.(php|blade\.php)$/', $path)) {
            $out[$path] = file_get_contents($path);
        }
    }

    return $out;
}

function methodBody(ReflectionMethod $m): string
{
    $file = $m->getFileName();
    if ($file === false || $m->getStartLine() === false) {
        return '';
    }
    $lines = file($file);

    return implode('', array_slice($lines, $m->getStartLine() - 1, $m->getEndLine() - $m->getStartLine() + 1));
}

/** Properties this method assigns: `$this->foo = …`. */
function assignedProperties(string $body): array
{
    preg_match_all('/\$this->([a-zA-Z_][a-zA-Z0-9_]*)\s*(?:\[[^\]]*\])?\s*=(?!=)/', $body, $m);

    return array_values(array_unique($m[1]));
}

/** Does this body READ $this->prop (any mention that is not an assignment target)? */
function readsProperty(string $body, string $prop): bool
{
    $mentions = preg_match_all('/\$this->'.preg_quote($prop, '/').'\b/', $body);
    if ($mentions === 0) {
        return false;
    }
    $writes = preg_match_all('/\$this->'.preg_quote($prop, '/').'\s*(?:\[[^\]]*\])?\s*=(?!=)/', $body);

    return $mentions > $writes;
}

$sources = productionSources($repo);
$failures = [];

// The public API surface: the classes the docs present as references.
$pages = [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($repo.'/docs'));
foreach ($rii as $f) {
    if ($f->isFile() && $f->getExtension() === 'md') {
        $pages[] = $f->getPathname();
    }
}

$classes = [];
foreach ($pages as $page) {
    $md = file_get_contents($page);
    if (! preg_match('/^#\s+(.+)$/m', $md, $h1)) {
        continue;
    }
    preg_match_all('/^use (NyonCode\\\\[A-Za-z0-9_\\\\]+);$/m', $md, $uses);
    foreach ($uses[1] as $fqcn) {
        if (str_ends_with($fqcn, '\\'.trim($h1[1])) && class_exists($fqcn)) {
            $classes[$fqcn] = true;
        }
    }
}
ksort($classes);

$checked = 0;

/** @var array<string, true> Setters already checked, keyed by declaring class::method. */
$seenSetters = [];

foreach (array_keys($classes) as $fqcn) {
    $class = new ReflectionClass($fqcn);
    if ($class->isAbstract()) {
        continue;
    }

    // Every method that could read a property: the class and its parents.
    //
    // Keyed by declaring class AND name, so an override does not hide the version
    // it overrides. A child that narrows a reader typically still calls
    // `parent::…()` — BelongsToSelect does exactly that — and the parent's body is
    // where the property is actually read. Keying by name alone kept only the
    // child's body and reported a live setter as dead.
    $allMethods = [];
    for ($c = $class; $c !== false; $c = $c->getParentClass()) {
        foreach ($c->getMethods() as $m) {
            $allMethods[$m->getDeclaringClass()->getName().'::'.$m->getName()] ??= $m;
        }
    }

    foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $setter) {
        // Check each setter once, against the first documented class that offers
        // it — not once per class, which would report an inherited setter 40×.
        //
        // Keyed by where it is DECLARED rather than skipping anything not
        // declared on this class: a setter living in a shared concern (HasHint,
        // HasExtraAttributes) reports its declaring class as the base component
        // the trait is mixed into, and that base is abstract, so it is never
        // scanned on its own. Skipping those left every concern-provided setter
        // unchecked — which is how hintIcon(), hintColor() and
        // extraInputAttributes() stayed inert across 40-odd types without this
        // gate noticing.
        $origin = $setter->getDeclaringClass()->getName().'::'.$setter->getName();
        if (isset($seenSetters[$origin])) {
            continue;
        }
        $seenSetters[$origin] = true;

        if ($setter->isStatic() || $setter->isConstructor()) {
            continue;
        }

        $doc = (string) $setter->getDocComment();
        if (str_contains($doc, '@dead-api-ignore')) {
            continue;
        }

        $type = $setter->getReturnType();
        $name = $type instanceof ReflectionNamedType ? $type->getName() : null;
        if ($name !== 'static' && $name !== 'self') {
            continue; // not a fluent setter
        }

        $body = methodBody($setter);
        $props = assignedProperties($body);
        if ($props === []) {
            continue; // delegates elsewhere (e.g. ->czk() calling ->money())
        }

        $checked++;

        foreach ($props as $prop) {
            // Who reads it? Any method other than this setter.
            $readers = [];
            foreach ($allMethods as $m) {
                $mName = $m->getName();
                if ($mName === $setter->getName()) {
                    continue;
                }
                if (readsProperty(methodBody($m), $prop)) {
                    $readers[] = $mName;
                }
            }

            // The property may also be read straight from a Blade view of this
            // package (`$column->prop` is rare, but `@php $x = $field->prop` exists).
            if ($readers === []) {
                $failures[] = "{$class->getShortName()}::{$setter->getName()}() writes \${$prop}, which nothing ever reads";

                continue;
            }

            // A reader only counts if production code can actually reach it —
            // by a direct call, a Blade call, or a dynamic method_exists() name.
            $reachable = false;
            foreach ($readers as $reader) {
                foreach ($sources as $path => $code) {
                    if ($path === $setter->getFileName()) {
                        continue; // the declaring file calling itself proves nothing
                    }
                    if (preg_match('/->'.preg_quote($reader, '/').'\s*\(/', $code)
                        || preg_match('/[\'"]'.preg_quote($reader, '/').'[\'"]/', $code)) {
                        $reachable = true;
                        break 2;
                    }
                }
            }

            if (! $reachable) {
                $failures[] = "{$class->getShortName()}::{$setter->getName()}() writes \${$prop}, read only by ".
                    implode('/', array_map(fn ($r) => $r.'()', $readers)).' — which no production code calls';
            }
        }
    }
}

$failures = array_values(array_unique($failures));
sort($failures);

/*
 * Baseline: the dead setters already in the tree when this gate was added.
 *
 * All 34 were confirmed independently — by searching production code for the
 * getter, for a dynamic 'getter' string, for external `$x->prop` reads, and (the
 * check this tool itself cannot do) for any subclass reading `$this->prop`.
 * Nothing reaches any of them, so the list is verdicts, not leads.
 *
 * They are recorded rather than fixed because there are too many to do in one
 * go, and a gate that is red from birth teaches everyone to ignore it. The debt
 * stays counted while any NEW dead setter fails immediately.
 *
 * Notable ones, in case the count makes them easy to skim past:
 *   - DateTimePicker::format() / displayFormat() / timezone() — the whole
 *     "Format" section of its docs configures values the field never reads.
 *   - Section::aside() — a documented "side-by-side layout" that does nothing.
 *   - StackedColumn::searchable() / searchColumns() — multi-column search.
 *   - ToggleColumn::onIcon() / offIcon(), TextColumn::fontFamily().
 */
$baselineFile = $repo.'/scripts/dead-api-baseline.txt';

if (in_array('--update-baseline', $argv, true)) {
    file_put_contents($baselineFile, implode("\n", $failures)."\n");
    echo 'dead-api baseline updated — '.count($failures)." known dead setter(s) recorded.\n";
    exit(0);
}

$baseline = is_file($baselineFile)
    ? array_values(array_filter(array_map('trim', file($baselineFile))))
    : [];

$new = array_values(array_diff($failures, $baseline));
$fixed = array_values(array_diff($baseline, $failures));

if ($new !== []) {
    echo 'dead-api FAILED — '.count($new)." new dead setter(s):\n";
    foreach ($new as $f) {
        echo "  ✗ $f\n";
    }
    echo "\nWire it up, delete it, or mark it @dead-api-ignore with a reason.\n";
    exit(1);
}

echo "dead-api OK — $checked fluent setters on ".count($classes).' documented classes';
if ($baseline !== []) {
    echo ', '.count($baseline).' known dead in the baseline';
    echo $fixed !== [] ? '; '.count($fixed).' now fixed — refresh with --update-baseline' : '';
}
echo ".\n";
