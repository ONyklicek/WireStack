<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Contracts\WireException;

/**
 * ADR 0022's exception contract, and the only thing that enforces it.
 *
 * The rules are written in AI_CODING_STANDARD.md § Exceptions and were checked
 * by review, which is to say by nobody. `ResourceRoutingException` shipped
 * without `WireException` and stayed that way — the one clause the marker exists
 * for, `catch (WireException)`, silently did not catch resource routing
 * failures. The per-package `ExceptionContractTest` in wire-table could not
 * notice, because it names four exceptions by hand.
 *
 * This one names none. It reads the filesystem, so a class that is added tomorrow
 * is held to the same rules on the day it is added.
 *
 * Three rules, each with its own failure mode:
 *
 *   1. Every class under a package's `Exceptions/` implements `WireException`.
 *      Without it the class is uncatchable as part of the stack.
 *   2. Every one of them is `final`. A subclass in application code would inherit
 *      named constructors that return `self`, which is the parent, not the child.
 *   3. Every one of them extends an SPL exception, so an application that already
 *      catches `RuntimeException` or `InvalidArgumentException` keeps working —
 *      the backwards-compatibility half of ADR 0022.
 *
 * Plus two more, pointing the other way. No throw site in package source may
 * raise a bare SPL exception — that is what a domain class replaces, and every
 * one of them was a message someone had already written carefully into a
 * `RuntimeException` where nothing could catch it by meaning. And no class may
 * hand back an error shape instead of throwing one, which is the same failure
 * turned inside out: the caller gets a value that looks like an answer.
 */

/**
 * Every exception class file in the monorepo, as relative path => class name.
 *
 * @return array<string, class-string>
 */
function wireExceptionClasses(): array
{
    $root = dirname(__DIR__, 2);
    $classes = [];

    foreach (glob($root.'/packages/*/src/Exceptions/*.php') ?: [] as $file) {
        $source = (string) file_get_contents($file);

        if (! preg_match('/^namespace\s+([^;]+);/m', $source, $namespace)) {
            continue;
        }

        $classes[str_replace($root.'/', '', $file)] = trim($namespace[1]).'\\'.basename($file, '.php');
    }

    return $classes;
}

/**
 * Every PHP file under every package's src, excluding the Exceptions directory.
 *
 * @return array<int, string>
 */
function wirePackageSourceFiles(): array
{
    $root = dirname(__DIR__, 2);
    $files = [];

    foreach (glob($root.'/packages/*/src', GLOB_ONLYDIR) ?: [] as $src) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src));

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();

            if (str_contains($path, '/src/Exceptions/')) {
                continue;
            }

            $files[] = str_replace($root.'/', '', $path);
        }
    }

    sort($files);

    return $files;
}

it('finds the exception classes it is meant to be checking', function () {
    // A guard on the test itself: a glob that stops matching would otherwise
    // turn this whole file into three vacuous passes.
    expect(wireExceptionClasses())->toHaveCount(count(wireExceptionClasses()))
        ->and(count(wireExceptionClasses()))->toBeGreaterThan(20);
});

it('makes every exception catchable as part of the stack', function () {
    $offenders = [];

    foreach (wireExceptionClasses() as $file => $class) {
        if (! is_a($class, WireException::class, true)) {
            $offenders[] = $file;
        }
    }

    expect($offenders)->toBe([], implode("\n", [
        'These exceptions do not implement WireException, so `catch (WireException)`',
        'does not catch them (ADR 0022):',
        ...$offenders,
    ]));
});

it('keeps every exception final', function () {
    $offenders = [];

    foreach (wireExceptionClasses() as $file => $class) {
        if (! (new ReflectionClass($class))->isFinal()) {
            $offenders[] = $file;
        }
    }

    expect($offenders)->toBe([], implode("\n", [
        'These exceptions are not final. Their static named constructors return',
        '`self`, so a subclass would silently get instances of the parent:',
        ...$offenders,
    ]));
});

it('keeps every exception on an SPL base a caller may already catch', function () {
    $offenders = [];

    foreach (wireExceptionClasses() as $file => $class) {
        $isSpl = is_a($class, RuntimeException::class, true)
            || is_a($class, LogicException::class, true);

        if (! $isSpl) {
            $offenders[] = $file;
        }
    }

    expect($offenders)->toBe([], implode("\n", [
        'These exceptions extend neither RuntimeException nor LogicException, so an',
        'application already catching the SPL class the site used to throw is broken',
        'by them (ADR 0022):',
        ...$offenders,
    ]));
});

it('throws no bare SPL exception from package source', function () {
    // Bare means "constructed at the throw site with no domain class". The point
    // is not that RuntimeException is wrong — every domain class here extends it
    // — but that a caller can only catch it by base, never by meaning, and the
    // careful message is invisible to anything but a human reading a stack trace.
    $spl = implode('|', [
        'Exception',
        'ErrorException',
        'RuntimeException',
        'LogicException',
        'InvalidArgumentException',
        'DomainException',
        'LengthException',
        'OutOfRangeException',
        'OutOfBoundsException',
        'OverflowException',
        'RangeException',
        'UnderflowException',
        'UnexpectedValueException',
        'BadFunctionCallException',
        'BadMethodCallException',
    ]);

    $offenders = [];

    foreach (wirePackageSourceFiles() as $file) {
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/'.$file);

        foreach (explode("\n", $source) as $number => $line) {
            if (preg_match('/throw new \\\\?('.$spl.')\s*\(/', $line)) {
                $offenders[] = $file.':'.($number + 1);
            }
        }
    }

    expect($offenders)->toBe([], implode("\n", [
        'These sites throw a bare SPL exception. Give the failure a class in the',
        "owning package's Exceptions/ with a static named constructor, keeping the",
        'same SPL base so existing catches still work (ADR 0022):',
        ...$offenders,
    ]));
});

it('returns no error shape from package source', function () {
    // `return ['error' => …]` is banned by AI_CODING_STANDARD.md § Exceptions,
    // and TableIntrospector::queryPlan() shows why it is worth a test rather
    // than a rule: every other return from that method carried a `query_plan`
    // key, so the error shape read as "this table plans nothing" to anyone who
    // did not know to check — and nothing did check, because no other return
    // had an `error` key to compare against.
    //
    // A boundary that must not throw still catches and formats there: wire-boost's
    // MCP tools answer with `Response::error()`, and a Livewire host returns
    // `['success' => false, 'message' => …]` to a browser that cannot catch. Both
    // are shapes a caller can act on; this one was not.
    $offenders = [];

    foreach (wirePackageSourceFiles() as $file) {
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/'.$file);

        foreach (explode("\n", $source) as $number => $line) {
            if (preg_match('/return\s*\[\s*[\'"]error[\'"]\s*=>/', $line)) {
                $offenders[] = $file.':'.($number + 1);
            }
        }
    }

    expect($offenders)->toBe([], implode("\n", [
        'These sites return an error shape instead of throwing. Throw the failure and',
        'let the layer that can answer for it catch — the MCP tool boundary, the',
        'Livewire host (ADR 0022 §4):',
        ...$offenders,
    ]));
});
