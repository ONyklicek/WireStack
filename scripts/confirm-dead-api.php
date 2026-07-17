<?php

declare(strict_types=1);

/*
 * Independent confirmation of the dead-api baseline (`php scripts/confirm-dead-api.php .`).
 *
 * Kept alongside the gate so the baseline can be re-confirmed rather than
 * trusted: a checker nobody audits is just a second opinion from the same brain.
 *
 * Deliberately NOT the tool's logic: it searches production code for ANY access
 * to the value — the getter by name, a dynamic 'getter' string, and the property
 * itself (`->prop`, `$this->prop`) which the tool never looks for outside the
 * declaring class. A subclass or Blade reading the property directly would make
 * the tool's verdict wrong, and that is exactly what this must catch.
 */

$repo = rtrim($argv[1] ?? getcwd(), '/');
require $repo.'/vendor/autoload.php';

$sources = [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($repo.'/packages'));
foreach ($rii as $f) {
    if (! $f->isFile()) {
        continue;
    }
    $p = $f->getPathname();
    if (str_contains($p, '/tests/') || str_contains($p, '/vendor/') || str_contains($p, '/node_modules/')) {
        continue;
    }
    if (preg_match('/\.(php|blade\.php|js)$/', $p)) {
        $sources[$p] = file_get_contents($p);
    }
}

$baseline = file($repo.'/scripts/dead-api-baseline.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$declaringFile = function (string $short): ?string {
    foreach (['Components', 'Columns', 'Filters', 'Schema'] as $ns) {
        foreach (glob($GLOBALS['repo'].'/packages/*/src/**/'.$short.'.php') as $g) {
            return $g;
        }
    }

    return null;
};

$confirmed = 0;
$suspect = [];

foreach ($baseline as $line) {
    if (! preg_match('/^([A-Za-z]+)::([a-zA-Z]+)\(\) writes \$([a-zA-Z]+)(?:, read only by ([a-zA-Z\/()]+))?/', $line, $m)) {
        echo "?? unparsed: $line\n";

        continue;
    }
    [$all, $class, $setter, $prop] = $m;
    $readers = isset($m[4]) && $m[4] !== ''
        ? array_map(fn ($r) => rtrim($r, '()'), explode('/', $m[4]))
        : [];

    $hits = [];

    foreach ($sources as $path => $code) {
        $isOwnClass = str_ends_with($path, "/$class.php");

        // 1. the getter, called on something
        foreach ($readers as $reader) {
            if (! $isOwnClass && preg_match('/->'.preg_quote($reader, '/').'\s*\(/', $code)) {
                $hits[] = "$reader() called in ".basename($path);
            }
            // 2. the getter, named dynamically (method_exists / call_user_func)
            if (! $isOwnClass && preg_match('/[\'"]'.preg_quote($reader, '/').'[\'"]/', $code)) {
                $hits[] = "'$reader' referenced in ".basename($path);
            }
        }

        // 3. the property read from OUTSIDE via an instance ($field->prop).
        //    Must exclude $this->prop: an unrelated class having a property of
        //    the same name (ColorPicker::$format vs DateTimePicker::$format) is
        //    a name collision, not a consumer.
        if (! $isOwnClass && preg_match('/(?<!\$this)->'.preg_quote($prop, '/').'\b(?!\s*\()/', $code)) {
            $hits[] = "\$$prop read externally in ".basename($path);
        }
    }

    // 4. A subclass reading $this->prop directly — the tool walks parents, never
    //    children, so this is its real blind spot.
    foreach (get_declared_classes() as $candidate) {
        if (! str_starts_with($candidate, 'NyonCode\\')) {
            continue;
        }
        $rc = new ReflectionClass($candidate);
        $parent = $rc->getParentClass();
        $isChild = false;
        while ($parent !== false) {
            if ($parent->getShortName() === $class) {
                $isChild = true;
                break;
            }
            $parent = $parent->getParentClass();
        }
        if (! $isChild) {
            continue;
        }
        foreach ($rc->getMethods() as $m) {
            if ($m->getDeclaringClass()->getName() !== $candidate || $m->getFileName() === false) {
                continue;
            }
            $lines = file($m->getFileName());
            $body = implode('', array_slice($lines, $m->getStartLine() - 1, $m->getEndLine() - $m->getStartLine() + 1));
            if (preg_match('/\$this->'.preg_quote($prop, '/').'\b(?!\s*(?:\[[^\]]*\])?\s*=(?!=))/', $body)) {
                $hits[] = "subclass {$rc->getShortName()}::{$m->getName()}() reads \$$prop";
            }
        }
    }

    if ($hits === []) {
        $confirmed++;
    } else {
        $suspect[] = "$class::$setter() — ".implode('; ', array_slice(array_unique($hits), 0, 2));
    }
}

echo "confirmed dead: $confirmed / ".count($baseline)."\n";
if ($suspect !== []) {
    echo "\nNeeds a human — a name collision reads like a consumer here:\n";
    foreach ($suspect as $s) {
        echo "  ! $s\n";
    }
    echo "\nKnown and already judged:\n";
    echo "  Radio::boolean() — the hit is \$filter->boolean in ApplyFilters (the query\n";
    echo "  pipeline's own property, unrelated to Radio). isBoolean() has no caller and\n";
    echo "  radio.blade.php never mentions it: dead. Hand-checked 2026-07-15.\n";
}
