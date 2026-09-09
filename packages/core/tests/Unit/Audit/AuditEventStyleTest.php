<?php

declare(strict_types=1);

use NyonCode\WireCore\Audit\AuditEventStyle;

it('gives every event core records a role and a glyph', function () {
    foreach (['created', 'updated', 'cell_updated', 'deleted', 'bulk_action'] as $event) {
        expect(AuditEventStyle::color($event))->not->toBe('gray')
            ->and(AuditEventStyle::icon($event))->toBeString();
    }
});

it('colours updated and cell_updated the same', function () {
    // The same thing at two grains. A timeline that coloured them apart would be
    // claiming a distinction the reader does not have.
    expect(AuditEventStyle::color('cell_updated'))->toBe(AuditEventStyle::color('updated'))
        ->and(AuditEventStyle::icon('cell_updated'))->toBe(AuditEventStyle::icon('updated'));
});

it('degrades to the neutral treatment for an event nobody mapped', function () {
    // The set is open: an application may dispatch any string it likes, and a
    // wrong colour is worse than no colour.
    expect(AuditEventStyle::color('invoice_sent'))->toBe('gray')
        ->and(AuditEventStyle::icon('invoice_sent'))->toBeNull();
});

it('renders the timeline chrome through the palette', function () {
    expect(AuditEventStyle::iconBgClass('created'))->toContain('bg-emerald-100')
        ->and(AuditEventStyle::iconTextClass('created'))->toContain('text-emerald-600')
        ->and(AuditEventStyle::iconBgClass('deleted'))->toContain('bg-red-100')
        ->and(AuditEventStyle::iconTextClass('bulk_action'))->toContain('text-amber-600');
});

it('draws an update in the info role, not a hue of its own', function () {
    // The timeline drew this blue before the map existed, which was `info`
    // rendering as one colour here and cyan everywhere else. One role, one hue.
    expect(AuditEventStyle::iconBgClass('updated'))->toContain('bg-cyan-100')
        ->and(AuditEventStyle::iconTextClass('updated'))->toContain('text-cyan-600');
});

it('moves the timeline when an application re-points a role', function () {
    // The whole point of reading the palette rather than writing a hue: a teal
    // success reaches the audit trail too.
    config()->set('wire-core.colors.success', 'teal');

    expect(AuditEventStyle::iconBgClass('created'))->toContain('bg-teal-100')
        ->and(AuditEventStyle::iconTextClass('created'))->toContain('text-teal-600');
});

it('lists every mapped type and its role', function () {
    // What wire-module-audit's badge map is built from, so the two cannot drift.
    expect(AuditEventStyle::colors())->toBe([
        'created' => 'success',
        'updated' => 'info',
        'cell_updated' => 'info',
        'deleted' => 'danger',
        'bulk_action' => 'warning',
    ]);
});
