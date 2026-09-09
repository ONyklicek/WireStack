<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAudit\Support;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use NyonCode\WireCore\Audit\AuditEventStyle;

/**
 * What kind of thing happened — named once, for every surface that shows it.
 *
 * `wire-core` writes five types (`AuditLogger`, and the list
 * `wire-core.audit.events` accepts), and the set is **open**: an application may
 * dispatch its own `AuditableEvent` with any string it likes. So this answers
 * for the five it knows and degrades to a headline for the rest, rather than
 * pretending the list is closed — the module shipped knowing three of them,
 * which left `bulk_action` and `cell_updated` rows with no colour, no filter and
 * their raw database string on screen.
 *
 * **The colours are core's**, and now literally so: they are read from
 * {@see AuditEventStyle}, which is what the trail
 * slide-over draws from too. This used to be a second copy of that map kept in
 * step by hand — the same event shown in two places in two colours is worse
 * than either colour alone, and a copy is how that happens.
 *
 * The labels are not core's, and that is deliberate too: core's
 * `wire-core::audit.event_*` are sentence fragments for a timeline ("updated
 * this record"), which is the right shape there and the wrong one inside a
 * badge or a filter.
 */
final class AuditEvents
{
    /**
     * The event types `wire-core`'s logger writes.
     *
     * @var array<int, string>
     */
    public const TYPES = [
        'created',
        'updated',
        'deleted',
        'bulk_action',
        'cell_updated',
    ];

    /**
     * The label for one type — translated where this module knows the type, and
     * a readable version of the stored string where it does not.
     */
    public static function label(string $type): string
    {
        $key = 'wire-module-audit::messages.event_'.$type;

        return Lang::has($key) ? (string) __($key) : Str::headline($type);
    }

    /**
     * Filter options, over the types core records.
     *
     * Not over the types this log *holds* — the way the record-type filter is
     * built. The difference is that this list is closed and short: an event a
     * clean installation has not produced yet is still worth offering, whereas
     * a model nobody audits is not.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::TYPES as $type) {
            $options[$type] = self::label($type);
        }

        return $options;
    }

    /**
     * The badge colour map, keyed the way a badge resolves it.
     *
     * A `BadgeColumn` resolves its colour from the state it renders, and the
     * state here is the label — so the map is keyed by label and built from the
     * same source, which is what stops the two from drifting.
     *
     * @return array<string, string>
     */
    public static function colors(): array
    {
        $colors = [];

        foreach (AuditEventStyle::colors() as $type => $color) {
            $colors[self::label($type)] = $color;
        }

        return $colors;
    }

    /** The colour for one type; grey for a type nobody mapped. */
    public static function color(string $type): string
    {
        return AuditEventStyle::color($type);
    }
}
