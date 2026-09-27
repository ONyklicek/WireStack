<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Routing\Concerns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Registration\Contracts\HasRegistryKey;
use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;
use NyonCode\WireCore\Foundation\Routing\Zone;

/**
 * Where one of this class's pages is, asked of the class itself.
 *
 *   OrderResource::url();                    // /admin/orders
 *   OrderResource::url('edit', $order);      // /admin/orders/7/edit
 *   TaskBoard::url(parameters: ['week' => 12]);   // /admin/board?week=12
 *
 * A forwarder, and only that. The answer has always been
 * {@see ResolvesPageUrls::urlFor()} — what this removes is the string key the
 * caller had to repeat: the class already knows it, so a renamed key can no
 * longer leave a link pointing at the old one.
 *
 * The zone defaults to the one being rendered, which is right on a full page
 * render and wrong inside a Livewire update, where the route is
 * `livewire.update` and {@see Zone::current()} answers null (ADR 0027). A
 * component that builds links on every round trip keeps the zone it read on
 * mount and passes it.
 *
 * Null is a real answer, as it is from the contract: a class that declares no
 * such page, a zone that does not route it, or an application with no routing
 * package at all.
 *
 * @phpstan-require-implements HasRegistryKey
 */
trait InteractsWithPageUrls
{
    /**
     * The URL of one of this class's pages, or null when it is not routed.
     *
     * @param  string  $page  A page kind — `index`, `create`, `view`, `edit`, or one of the class's own.
     * @param  Model|int|string|null  $record  The record a record page is about; a model is reduced to its key.
     * @param  array<string, mixed>  $parameters  Further route parameters — `['parent' => …]` for a nested resource. Anything the route does not name becomes the query string.
     * @param  string|null  $zone  The mount point to answer for; null is the zone being rendered.
     */
    public static function url(
        string $page = 'index',
        Model|int|string|null $record = null,
        array $parameters = [],
        ?string $zone = null,
    ): ?string {
        if ($record !== null) {
            $parameters['record'] = $record;
        }

        // Keys, not models: `{record}` and `{parent}` are keys the page resolves
        // for itself (see routing.md), and a model's route key may be something
        // else — a slug — which the page would then fail to find.
        $parameters = array_map(
            static fn (mixed $value): mixed => $value instanceof Model ? $value->getKey() : $value,
            $parameters,
        );

        return app(ResolvesPageUrls::class)->urlFor(static::key(), $page, $parameters, $zone ?? Zone::current());
    }
}
