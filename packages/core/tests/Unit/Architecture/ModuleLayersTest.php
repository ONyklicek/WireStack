<?php

declare(strict_types=1);

/**
 * The layer rule for wire-core's modules, and the only thing that enforces it.
 *
 * ADR 0007 wrote a dependency matrix for four modules (Foundation, Actions,
 * Notifications, Modals) and named "code review + grep" as its enforcement.
 * Eleven modules and two years later the matrix was violated in 23 places,
 * including a bidirectional Actions <-> Modals cycle — the one shape that turns
 * a future extraction from a refactor into an impossibility, because two
 * packages requiring each other at `self.version` cannot be released.
 *
 * Nobody was ignoring the matrix. There was simply nothing to notice the drift.
 * This test is that thing.
 *
 * The layers:
 *
 *   L0  Foundation, Exceptions   — the base. Imports nothing above it.
 *   L1  Core                     — the headless engine. May see L0.
 *   L2  Actions, Modals, Notifications, Widgets, Infolists, Panels, Audit
 *                                — surfaces. May see L0 and L1, but not each
 *                                  other.
 *
 * Foundation and Exceptions are ONE layer, not two: nine classes under
 * `Exceptions/` extend `Foundation\Contracts\WireException` while Foundation
 * throws them, so any rule that orders the two is a rule that is already broken.
 *
 * L2 -> L1 is allowed, which is where this departs from ADR 0007. That matrix
 * said Actions may depend on Foundation only, but `InteractsWithActions` really
 * does drive `ActionPipeline` and `PluginManager`, and V2.4 adds more of it
 * (`TransitionAction` over `Core/Workflow`, `Queueable` over the job runner).
 * A rule the code has never obeyed and the plan intends to break further is not
 * a rule; it is a wish. The matrix follows the code here.
 *
 * Two lists below carry the exceptions, and the difference between them is the
 * whole point:
 *
 *   - `permittedCoreEdges()` — deliberate and permanent. Reviewed, documented,
 *     not going anywhere.
 *   - `coreLayerDebt()` — what was left over. 19 edges the day this was
 *     written; the ceilings in the last test are where it stands now. Each one
 *     is a thing to remove, not a thing to live with.
 *
 * Both are counted, so an already-listed file cannot quietly grow a new edge,
 * and both are checked for staleness, so an entry cannot outlive the import it
 * describes. The lists can shrink. They cannot grow without someone editing
 * this file, which is exactly the conversation that should happen.
 */
function coreModuleLayers(): array
{
    return [
        'Foundation' => 0,
        'Exceptions' => 0,
        'Core' => 1,
        'Actions' => 2,
        'Modals' => 2,
        'Notifications' => 2,
        'Widgets' => 2,
        'Infolists' => 2,
        'Panels' => 2,
        'Audit' => 2,
    ];
}

/**
 * Deliberate, permanent exceptions. Each is a composition that would cost more
 * to break than the boundary is worth.
 *
 * @return array<string, array<string, int>>
 */
function permittedCoreEdges(): array
{
    return [
        // A panel entry IS an infolist entry with a writable surface bolted on;
        // `EditableEntry` extends `Infolists\Components\Entry` and `Panel` is
        // built out of them. Splitting these two would mean duplicating the
        // entry vocabulary, which is the opposite of what the boundary is for.
        'Panels/Components/EditableEntry.php' => ['Infolists' => 1],
        'Panels/Panel.php' => ['Infolists' => 2],

        // The audit trail is surfaced AS an action. There is no version of this
        // module that does not reach for one.
        'Audit/Actions/AuditTrailAction.php' => ['Actions' => 1],
    ];
}

/**
 * The debt, exactly as it stood when the rule got its first enforcement.
 *
 * Ordered by how it is being paid off, not alphabetically.
 *
 * @return array<string, array<string, int>>
 */
function coreLayerDebt(): array
{
    return [
        // Foundation -> Actions on a signature. These want
        // `Foundation\Contracts\ActionContract` to point at instead.
        'Foundation/Contracts/HasFieldActions.php' => ['Actions' => 1],
        'Foundation/Concerns/HasActions.php' => ['Actions' => 1],
        'Foundation/Concerns/HasPrefixAndSuffix.php' => ['Actions' => 1],

        // Foundation -> Actions/Widgets from a docblock only — no runtime
        // coupling at all, since a `use` is a compile-time alias and PHP never
        // resolves it for an `@param`. They are still listed, because the
        // cheap-looking fix does not work here: inline the FQCN instead and
        // Pint's fully_qualified_strict_types rule puts the import straight
        // back, and `composer lint` is a required gate. So these two get paid
        // the same way the signatures above do — by naming a contract that
        // Foundation is allowed to see — not by moving text around.
        'Foundation/Schema/Section.php' => ['Actions' => 1],
        'Foundation/View/WidgetGrid.php' => ['Widgets' => 1],

        // What is left of the cycle, now one-directional: an action opens a
        // modal. `Modals\Wizard` no longer reaches back — it asks for
        // `Foundation\Contracts\WizardStep` instead of `Actions\ModalStep`.
        'Actions/Concerns/HasModal.php' => ['Modals' => 4, 'Infolists' => 1],

        // The action host trait. It is a Livewire host, not an Action, which is
        // why it reaches so wide — but it is still in the Actions module.
        'Actions/Concerns/InteractsWithActions.php' => [
            'Modals' => 1,
            'Infolists' => 3,
            'Notifications' => 2,
        ],
    ];
}

/**
 * Every cross-module import under `packages/core/src`, as
 * `relative/path.php => [TargetModule => count]`.
 *
 * Files sitting directly in `src/` (the service provider, `helpers.php`) are
 * the composition root and are exempt by definition — wiring every module
 * together is their job.
 *
 * @return array<string, array<string, int>>
 */
function coreCrossModuleEdges(): array
{
    $root = dirname(__DIR__, 3).'/src';
    $layers = coreModuleLayers();
    $edges = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relative = str_replace($root.'/', '', $file->getPathname());
        $parts = explode('/', $relative);

        if (count($parts) === 1) {
            continue;
        }

        $module = $parts[0];

        if (! isset($layers[$module])) {
            continue;
        }

        foreach (file($file->getPathname()) as $line) {
            if (! preg_match('/^use\s+NyonCode\\\\WireCore\\\\(\w+)\\\\/', trim($line), $match)) {
                continue;
            }

            $target = $match[1];

            if ($target === $module || ! isset($layers[$target])) {
                continue;
            }

            $forbidden = $layers[$target] > $layers[$module]
                || ($layers[$module] === 2 && $layers[$target] === 2);

            if ($forbidden) {
                $edges[$relative][$target] = ($edges[$relative][$target] ?? 0) + 1;
            }
        }
    }

    ksort($edges);

    return $edges;
}

/**
 * @param  array<string, array<string, int>>  $edges
 * @return array<int, string>
 */
function flattenCoreEdges(array $edges): array
{
    $flat = [];

    foreach ($edges as $file => $targets) {
        ksort($targets);

        foreach ($targets as $target => $count) {
            $flat[] = "{$file} -> {$target} ×{$count}";
        }
    }

    sort($flat);

    return $flat;
}

it('lets no module import across a layer it may not see', function () {
    $known = flattenCoreEdges(array_merge_recursive(
        permittedCoreEdges(),
        coreLayerDebt(),
    ));

    $found = flattenCoreEdges(coreCrossModuleEdges());

    expect(array_values(array_diff($found, $known)))->toBe([], <<<'TXT'
        A new import crosses a module boundary in wire-core.

        Layers: L0 Foundation+Exceptions · L1 Core · L2 Actions, Modals,
        Notifications, Widgets, Infolists, Panels, Audit. L2 may see L0 and L1,
        never another L2.

        Reach for a contract in Foundation, or the class_exists()/container seam
        that Actions\Concerns\HasLifecycle::resolveNotificationManagerClass()
        already uses. If the edge is genuinely unavoidable, add it to
        permittedCoreEdges() in this file with the reason — deliberately, in
        review, which is the point.
        TXT);
});

it('keeps no entry in its lists that the code has outgrown', function () {
    $listed = array_merge_recursive(permittedCoreEdges(), coreLayerDebt());
    $found = coreCrossModuleEdges();

    $stale = array_values(array_diff(
        flattenCoreEdges($listed),
        flattenCoreEdges($found),
    ));

    expect($stale)->toBe([], <<<'TXT'
        An edge listed in this file no longer exists in the code, or exists
        fewer times than listed.

        That is good news — it means the debt got paid. Lower the count, or drop
        the entry. The lists are a ratchet: they may shrink and may not grow.
        TXT);
});

it('carries the debt it was written with, and no more', function () {
    // Without this, the first test has an obvious escape hatch: a new forbidden
    // import can be silenced by adding it to coreLayerDebt(). These two ceilings
    // are what make that a visible act. They are the numbers as of the day the
    // rule got enforced — 13 file/target pairs, 19 imports — and they ratchet
    // down as the debt is paid, never up. Down to 10 and 16 since.
    expect(count(flattenCoreEdges(coreLayerDebt())))->toBeLessThanOrEqual(10)
        ->and(array_sum(array_map(
            fn (array $targets): int => array_sum($targets),
            coreLayerDebt(),
        )))->toBeLessThanOrEqual(16);
});
