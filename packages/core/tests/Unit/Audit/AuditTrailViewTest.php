<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;
use NyonCode\WireCore\Audit\AuditEntry;

/*
 * The trail slide-over's markup.
 *
 * Rendered by nothing until now, which is how it came to print a boolean `true`
 * as `1`: the value rule lived in a Blade ternary, where no test could reach it,
 * beside a second copy of the same rule in the audit module that had the boolean
 * case right. The two surfaces now share one partial over
 * `Foundation\ValueObjects\ChangeSet`, and this is what says so.
 */

/** One entry, with a diff, ready to draw. */
function atEntry(array $old, array $new, array $metadata = []): AuditEntry
{
    $entry = new AuditEntry([
        'event' => 'updated',
        'auditable_type' => 'App\\Models\\Order',
        'auditable_id' => 7,
        'old_values' => $old,
        'new_values' => $new,
        'metadata' => $metadata,
    ]);

    $entry->created_at = now();

    // The relation is *loaded* rather than queried: `user()` resolves
    // `config('wire-core.audit.user_model')`, which defaults to
    // `App\Models\User` — a class a package installation need not have, and
    // this suite does not. Setting it here is also what makes the "system"
    // branch the one under test, which is the honest state for a change nobody
    // was signed in for.
    $entry->setRelation('user', null);

    return $entry;
}

function atTrail(AuditEntry ...$entries): string
{
    return View::make('wire-core::audit.trail', ['entries' => $entries])->render();
}

it('draws one diff table with a row per field that moved', function () {
    $html = atTrail(atEntry(['status' => 'draft', 'total' => 100], ['status' => 'sent', 'total' => 250]));

    expect($html)->toContain('status')
        ->toContain('draft')
        ->toContain('sent')
        ->toContain('250')
        // One header for the diff, not one per field.
        ->and(substr_count($html, '<thead'))->toBe(1);
});

it('writes a boolean as true and false, the way the entry page does', function () {
    // The drift this consolidation removed. `(string) true` is `1`, and `1` in
    // an audit trail is indistinguishable from the integer that means something
    // else entirely.
    $html = atTrail(atEntry(['paid' => false], ['paid' => true]));

    expect($html)->toContain('true')
        ->toContain('false')
        ->not->toContain('>1<');
});

it('says a side is empty rather than leaving the cell blank', function () {
    $html = atTrail(atEntry(['note' => null], ['note' => 'hello']));

    expect($html)->toContain(__('wire-core::audit.empty'));
});

it('draws no diff table for an entry where nothing moved', function () {
    $html = atTrail(atEntry(['status' => 'sent'], ['status' => 'sent']));

    expect($html)->not->toContain('<table');
});

it('still draws the timeline around it', function () {
    // The consolidation touched the diff and nothing else; the event icon, the
    // actor line and the IP are what make it a trail rather than a table.
    $html = atTrail(atEntry(['status' => 'draft'], ['status' => 'sent'], ['ip' => '10.0.0.1']));

    expect($html)->toContain(__('wire-core::audit.system'))
        ->toContain(__('wire-core::audit.event_updated'))
        ->toContain('10.0.0.1');
});

it('says so when there is no trail at all', function () {
    expect(atTrail())->toContain(__('wire-core::audit.no_entries'));
});
