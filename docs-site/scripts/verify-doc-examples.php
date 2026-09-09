<?php

declare(strict_types=1);
use NyonCode\WireAdmin\WireAdminServiceProvider;
use NyonCode\WireCore\WireCoreServiceProvider;
use NyonCode\WireForms\WireFormsServiceProvider;
use NyonCode\WirePanels\WirePanelsServiceProvider;
use NyonCode\WireSortable\WireSortableServiceProvider;
use NyonCode\WireTable\WireTableServiceProvider;
use Orchestra\Testbench\Foundation\Application;

/*
 * Example-code gate — every fluent call in a docs example must exist on the
 * class it is written on. The three other docs gates read the prose, the links
 * and the API sections; nothing read the *examples*, which is where a reader
 * copies from. Both defects it was written for had shipped:
 *
 *   1. An import of a class that does not exist — `WireForms\Components\DatePicker`,
 *      written from memory. The page built, linked and passed every gate.
 *   2. A call that throws — `SelectColumn::make('status')->rules([...])`, which is
 *      a BadMethodCallException: `rules()` is TextInputColumn's, and the shared
 *      editable-column vocabulary is `editableRules()`.
 *
 * ## Why it boots the framework
 *
 * Reflection alone is not enough, and getting this wrong produces confident
 * nonsense. `Action::make('edit')->onDoubleClick()` is not declared anywhere on
 * Action — it is a macro registered by WireTableServiceProvider, so a check that
 * skips the boot reports the correct page as broken. The app is booted first and
 * `hasMacro()` consulted alongside `hasMethod()`.
 *
 * ## What it will not claim
 *
 * Silence beats a wrong answer, so the checker gives up rather than guess when:
 *
 *   - the subject is a variable (`$table->…`, `$form->…`) — nothing says what it is;
 *   - the subject is a class the page never imported;
 *   - the class has a hand-written `__call` forwarder (RecordAction wraps an
 *     Action and answers to everything it answers to). Macroable's own `__call`
 *     is not that: it answers only to registered macros, so those stay checked;
 *   - the page registers the macro itself (`Action::macro('adminOnly', …)` in the
 *     plugin examples) — a name defined on the page counts as defined.
 *
 * Chains are tracked with a bracket-depth stack, which is the whole trick: inside
 * `->schema([ TextInput::make(…) ])` the inner class owns the calls at that depth
 * only, so the `->successMessage()` that follows at the outer level is not blamed
 * on TextInput. Declared return types are followed too, including a macro's — that
 * is how `->onDoubleClick()->behaviorOnly()` reads as RecordAction's method.
 *
 *   php docs-site/scripts/verify-doc-examples.php [repo-root]
 *   php docs-site/scripts/verify-doc-examples.php . --en      # English pages only
 */

$repo = rtrim($argv[1] ?? getcwd(), '/');
if ($repo === '' || str_starts_with($repo, '--')) {
    $repo = getcwd();
}

if (! is_file($repo.'/vendor/autoload.php')) {
    fwrite(STDERR, "No vendor/autoload.php under $repo — pass the repository root as the first argument.\n");
    exit(2);
}

require $repo.'/vendor/autoload.php';

if (! class_exists(Application::class)) {
    fwrite(STDERR, "verify-doc-examples needs orchestra/testbench (a dev dependency) so that\n");
    fwrite(STDERR, "service-provider macros are registered. Run composer install WITHOUT --no-dev.\n");
    exit(2);
}

/*
 * A real application, so every provider has registered its macros. Without this
 * the record-action triggers look like typos.
 */
$app = Application::create(
    basePath: $repo.'/vendor/orchestra/testbench-core/laravel',
    options: ['enables_package_discoveries' => true],
);

foreach ([
    WireCoreServiceProvider::class,
    WireFormsServiceProvider::class,
    WireTableServiceProvider::class,
    WirePanelsServiceProvider::class,
    WireSortableServiceProvider::class,
    WireAdminServiceProvider::class,
] as $provider) {
    if (class_exists($provider)) {
        $app->register($provider);
    }
}

$app->boot();

$onlyEn = in_array('--en', $argv, true);

$reflect = [];
$rc = static function (string $fqcn) use (&$reflect): ?ReflectionClass {
    if (! array_key_exists($fqcn, $reflect)) {
        $reflect[$fqcn] = class_exists($fqcn) ? new ReflectionClass($fqcn) : null;
    }

    return $reflect[$fqcn];
};

/** Declared, inherited, registered as a macro, or defined by the page itself. */
$responds = static function (ReflectionClass $class, string $method, array $pageMacros): bool {
    if ($class->hasMethod($method) || in_array($method, $pageMacros, true)) {
        return true;
    }

    if ($class->hasMethod('hasMacro') && $class->getName()::hasMacro($method)) {
        return true;
    }

    // A hand-written __call forwards to something this cannot see; Macroable's
    // does not — it throws for anything unregistered, so it stays checkable.
    if ($class->hasMethod('__call')) {
        $file = str_replace('\\', '/', (string) $class->getMethod('__call')->getFileName());

        return ! str_ends_with($file, 'Traits/Macroable.php');
    }

    return false;
};

/** What the chain becomes after this call, when the signature says so. */
$becomes = static function (ReflectionClass $class, string $method) use ($rc): ?ReflectionClass {
    $type = null;

    if ($class->hasMethod($method)) {
        $type = $class->getMethod($method)->getReturnType();
    } elseif ($class->hasMethod('hasMacro') && $class->getName()::hasMacro($method)) {
        // A macro has no method to reflect, but its closure does — and every
        // record-action trigger hands back a RecordAction.
        $property = new ReflectionProperty($class->getName(), 'macros');
        $macro = ($property->getValue())[$method] ?? null;
        $type = $macro instanceof Closure ? (new ReflectionFunction($macro))->getReturnType() : null;
    }

    if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
        return null;
    }

    return in_array($type->getName(), ['static', 'self', 'parent'], true) ? null : $rc($type->getName());
};

/** Bracket delta of a fragment, strings and comments already removed. */
$delta = static fn (string $code): int => substr_count($code, '(') + substr_count($code, '[') + substr_count($code, '{')
    - substr_count($code, ')') - substr_count($code, ']') - substr_count($code, '}');

$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($repo.'/docs'));
foreach ($iterator as $entry) {
    if ($entry->isFile() && str_ends_with($entry->getFilename(), '.md')) {
        $files[] = $entry->getPathname();
    }
}
sort($files);

$problems = [];
$counts = ['imports' => 0, 'statics' => 0, 'chained' => 0];

foreach ($files as $file) {
    $rel = substr($file, strlen($repo) + 1);
    if ($onlyEn && str_starts_with($rel, 'docs/cs/')) {
        continue;
    }

    $lines = explode("\n", (string) file_get_contents($file));

    // Page-wide, because a page may import at the top and use further down.
    $imports = [];
    $pageMacros = [];
    foreach ($lines as $line) {
        if (preg_match('/^\s*use (NyonCode\\\\[A-Za-z0-9_\\\\]+);\s*$/', $line, $m)) {
            $imports[substr($m[1], strrpos($m[1], '\\') + 1)] = $m[1];
        }
        if (preg_match('/::macro\(\s*[\'"]([a-zA-Z_][a-zA-Z0-9_]*)[\'"]/', $line, $m)) {
            $pageMacros[] = $m[1];
        }
    }

    $inBlock = false;
    $lang = '';
    $depth = 0;
    $stack = [];

    foreach ($lines as $index => $line) {
        $number = $index + 1;

        if (preg_match('/^\s*```(\w*)/', $line, $m)) {
            $inBlock = ! $inBlock;
            $lang = $inBlock ? $m[1] : '';
            $depth = 0;
            $stack = [];

            continue;
        }

        if (! $inBlock || ! in_array($lang, ['php', 'blade'], true)) {
            continue;
        }

        // Strings and comments first: a bracket inside either is not structure.
        $code = (string) preg_replace(
            ['/\/\/.*$/', "/'(?:\\\\.|[^'\\\\])*'/", '/"(?:\\\\.|[^"\\\\])*"/'],
            ['', "''", '""'],
            $line,
        );

        if (preg_match('/^\s*use (NyonCode\\\\[A-Za-z0-9_\\\\]+);\s*$/', $line, $m)) {
            $counts['imports']++;
            $fqcn = $m[1];
            if (! class_exists($fqcn) && ! interface_exists($fqcn) && ! trait_exists($fqcn) && ! enum_exists($fqcn)) {
                $problems[] = "$rel:$number — imports a class that does not exist: $fqcn";
            }

            continue;
        }

        while ($stack !== [] && end($stack)['depth'] > $depth) {
            array_pop($stack);
        }

        if (preg_match('/^\s*->([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $line, $m)) {
            $top = $stack === [] ? null : end($stack);
            if ($top !== null && $top['depth'] === $depth && $top['class'] !== null) {
                $counts['chained']++;
                if (! $responds($top['class'], $m[1], $pageMacros)) {
                    $problems[] = "$rel:$number — {$top['class']->getShortName()}::{$m[1]}() does not exist";
                } elseif (($next = $becomes($top['class'], $m[1])) !== null) {
                    $stack[count($stack) - 1]['class'] = $next;
                }
            }
        } elseif (preg_match('/^\s*(?:return\s+)?\$[a-zA-Z_][a-zA-Z0-9_]*\s*(->|$)/', $line)) {
            // A chain on a variable: claim nothing at this depth.
            $stack[] = ['class' => null, 'depth' => $depth];
        }

        if (preg_match_all('/\b([A-Z][A-Za-z0-9_]*)::([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $code, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $short = $match[1][0];
                $method = $match[2][0];
                // The depth AT the call, not at the start of the line.
                $callDepth = $depth + $delta(substr($code, 0, $match[0][1]));

                $class = isset($imports[$short]) ? $rc($imports[$short]) : null;
                if ($class === null) {
                    $stack[] = ['class' => null, 'depth' => $callDepth];

                    continue;
                }

                $counts['statics']++;
                if (! $responds($class, $method, $pageMacros) && ! $class->hasMethod('__callStatic')) {
                    $problems[] = "$rel:$number — {$short}::{$method}() does not exist";
                }

                $stack[] = ['class' => $class, 'depth' => $callDepth];
            }
        }

        $endsStatement = preg_match('/;\s*$/', rtrim($code)) === 1;

        $depth = max(0, $depth + $delta($code));

        if ($endsStatement) {
            while ($stack !== [] && end($stack)['depth'] >= $depth) {
                array_pop($stack);
            }
        }
    }
}

echo "checked {$counts['imports']} imports, {$counts['statics']} static calls, {$counts['chained']} chained calls\n";

if ($problems === []) {
    echo 'doc-examples OK — '.count($files)." pages, nothing in a code block contradicts the classes.\n";
    exit(0);
}

echo 'doc-examples FAILED — '.count($problems)." problem(s):\n";
foreach ($problems as $problem) {
    echo "  ✗ $problem\n";
}
echo "\nFix the example, or — if the call is real — check whether it comes from a macro\n";
echo "registered by a provider this script does not boot.\n";
exit(1);
