<?php

declare(strict_types=1);

/*
 * The dependency rule of ADR 0029 §5, read off the composer files that decide it.
 *
 * The rule has two halves and only one of them is about layering:
 *
 *   **Inside the stack, edges point down.** `wire-core → wire-forms → wire-table
 *   → wire-sortable → wire-panels → wire-admin` is a line, and a package may
 *   only require packages below it. This is what keeps the graph a graph: an
 *   upward or sideways edge makes "what does installing this pull in" a question
 *   nobody can answer from the diagram.
 *
 *   **Above the line, the stack is fair game.** `wire-admin` and every
 *   `wire-module-*` are consumers of the framework, exactly like an application:
 *   they may require anything in it, and the question for a new feature is
 *   whether it works there, not whether the package is allowed to ask for it.
 *
 * That second half is a correction, not a relaxation. `AI_BLUEPRINT.md` used to
 * say a module requires "nothing but wire-panels", which **no module has ever
 * done** — all five carry core, forms, table and panels — and the only package
 * ever held to it was `wire-module-auth`, whose ADR spent three measurements
 * defending a dependency the rest of the layer had taken in silence.
 *
 * What survives above the line is about optionality rather than layering, and it
 * is what this test is mostly here to hold: the shell stays optional (ADR 0028),
 * so no module may `require` it, and modules do not require each other, so
 * "install users" never means "install media too".
 */

/**
 * The stack, in the order its edges are allowed to point.
 *
 * @return array<string, int>
 */
function stackOrder(): array
{
    return [
        'nyoncode/wire-core' => 0,
        'nyoncode/wire-forms' => 1,
        'nyoncode/wire-table' => 2,
        'nyoncode/wire-sortable' => 3,
        'nyoncode/wire-panels' => 4,
        'nyoncode/wire-admin' => 5,
    ];
}

/**
 * Every package in this repository, with the in-repo packages it names.
 *
 * @return array<string, array{require: array<int, string>, suggest: array<int, string>}>
 */
function packageGraph(): array
{
    $graph = [];

    foreach (glob(dirname(__DIR__, 2).'/packages/*/composer.json') ?: [] as $file) {
        /** @var array{name: string, require?: array<string, string>, suggest?: array<string, string>} $manifest */
        $manifest = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

        $inRepo = static fn (array $names): array => array_values(array_filter(
            array_keys($names),
            static fn (string $name): bool => str_starts_with($name, 'nyoncode/wire-'),
        ));

        $graph[$manifest['name']] = [
            'require' => $inRepo($manifest['require'] ?? []),
            'suggest' => $inRepo($manifest['suggest'] ?? []),
        ];
    }

    return $graph;
}

it('keeps every edge inside the stack pointing down', function () {
    $order = stackOrder();
    $upward = [];

    foreach (packageGraph() as $package => $edges) {
        if (! isset($order[$package])) {
            continue;
        }

        foreach ($edges['require'] as $dependency) {
            // A stack package requiring something outside the stack — a module —
            // is upward by definition: modules sit above all of it.
            if (! isset($order[$dependency]) || $order[$dependency] >= $order[$package]) {
                $upward[] = "{$package} -> {$dependency}";
            }
        }
    }

    expect($upward)->toBe([], <<<'TXT'
        A package inside the stack requires one at or above its own level.

        The stack is a line — core → forms → table → sortable → panels → admin —
        and it stays one only while every edge points down. Reach for a contract
        in the lower package, or move the behaviour down to where both callers
        can see it. See ADR 0029 §5.
        TXT);
});

it('lets everything above the line use the whole stack', function () {
    // Not an assertion that they *do*, but that nothing here treats it as an
    // offence: this is the half the blueprint used to get wrong.
    expect(packageGraph()['nyoncode/wire-module-users']['require'])
        ->toContain('nyoncode/wire-forms')
        ->toContain('nyoncode/wire-table')
        ->and(packageGraph()['nyoncode/wire-module-auth']['require'])
        ->toContain('nyoncode/wire-forms');
});

it('keeps the shell optional, which is the one thing a module may not require', function () {
    // ADR 0028: nothing requires the shell, because naming the layout is the
    // opt-in. A module that requires it cannot be installed by an application
    // rendering its own — and `wire-suite` is the exception by definition, being
    // the dependency list an installer runs.
    $required = [];

    foreach (packageGraph() as $package => $edges) {
        if ($package === 'nyoncode/wire-suite') {
            continue;
        }

        if (in_array('nyoncode/wire-admin', $edges['require'], true)) {
            $required[] = $package;
        }
    }

    expect($required)->toBe([], 'A package requires the shell. Suggest it instead — see ADR 0029 §5.');
});

it('keeps one module from dragging another in', function () {
    $entangled = [];

    foreach (packageGraph() as $package => $edges) {
        if (! str_starts_with($package, 'nyoncode/wire-module-')) {
            continue;
        }

        foreach ($edges['require'] as $dependency) {
            if (str_starts_with($dependency, 'nyoncode/wire-module-')) {
                $entangled[] = "{$package} -> {$dependency}";
            }
        }
    }

    expect($entangled)->toBe([], <<<'TXT'
        One module requires another, so installing the first installs the second.

        A module is a business area an application chooses. If two of them share
        something, the shared part belongs in the stack below them — or the
        dependency is a `suggest`, which says the same thing without deciding it.
        TXT);
});
