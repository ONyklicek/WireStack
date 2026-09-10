<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Audit;

use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Foundation\Concerns\ResolvesColorClasses;

/**
 * What an audit event looks like — its palette role and its glyph, named once.
 *
 * The trail slide-over used to answer this itself, as a `@switch` with a literal
 * hue per case: `created` drawn `bg-emerald-100`, `deleted` drawn `bg-red-100`.
 * That is the same decision `AuditEvents::COLORS` makes in `wire-module-audit`,
 * written twice in two vocabularies — and that module's own docblock already
 * named the danger:
 *
 * > The same event shown in two places in two colours is worse than either
 * > colour alone.
 *
 * So the map lives here, in the package that owns the trail, and the module
 * reads it rather than keeping a second copy. What each role *renders* as is
 * still {@see HasColor}'s business, which
 * is what makes an application's re-pointed `success` reach the timeline too.
 *
 * The set is open by design: `AuditLogger` writes these five, an application may
 * dispatch any string it likes, and an event this does not know gets the neutral
 * treatment rather than a wrong colour.
 */
final class AuditEventStyle
{
    /**
     * The palette lives here rather than being reached for from the view.
     *
     * A Blade file asking `HasColor::…` directly is calling a static method on a
     * trait, which PHP 8.1 deprecated; more to the point, the timeline wants to
     * ask what an *event* looks like, not what a colour resolves to.
     *
     * The static half alone ({@see ResolvesColorClasses}): this class has no
     * colour of its own — it answers for an *event* — so `HasColor`'s instance
     * helpers, which all read `$this->getColor()`, have nothing to read here.
     */
    use ResolvesColorClasses;

    /**
     * Event type → [palette role, icon].
     *
     * `updated` and `cell_updated` share both, deliberately: they are the same
     * thing at two grains, and a timeline that coloured them apart would be
     * claiming a distinction the reader does not have.
     *
     * @var array<string, array{string, string}>
     */
    private const MAP = [
        'created' => ['success', 'outline:plus'],
        'updated' => ['info', 'outline:pencil'],
        'cell_updated' => ['info', 'outline:pencil'],
        'deleted' => ['danger', 'outline:trash'],
        'bulk_action' => ['warning', 'outline:queue-list'],
    ];

    /** The palette role for one event type, or `gray` for one nobody mapped. */
    public static function color(string $event): string
    {
        return self::MAP[$event][0] ?? 'gray';
    }

    /** The glyph for one event type, or null where the timeline draws its neutral dot. */
    public static function icon(string $event): ?string
    {
        return self::MAP[$event][1] ?? null;
    }

    /**
     * Every mapped type and its role.
     *
     * @return array<string, string>
     */
    public static function colors(): array
    {
        return array_map(static fn (array $style): string => $style[0], self::MAP);
    }

    /** The tinted circle behind the glyph, for one event type. */
    public static function iconBgClass(string $event): string
    {
        return self::getModalIconBgClass(self::color($event));
    }

    /** The glyph's own colour, for one event type. */
    public static function iconTextClass(string $event): string
    {
        return self::getModalIconTextClass(self::color($event));
    }
}
