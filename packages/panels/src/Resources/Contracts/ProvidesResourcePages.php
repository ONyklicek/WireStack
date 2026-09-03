<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Contracts;

use NyonCode\WirePanels\Routing\ResourceRoutes;
use NyonCode\WirePanels\Routing\RoutePage;

/**
 * A resource that names the pages which render it.
 *
 * Opt-in like every other surface contract, and for the same reason: a resource
 * that is registered for routing but reached only from inside another one — a
 * nested resource, an internal lookup — says so by not implementing this, rather
 * than by returning an empty array from a method it was forced to have.
 *
 * Declared rather than guessed. A helper that looked for
 * `App\Livewire\Resources\ListInvoices` by convention would work until someone
 * named a class differently, and then fail by quietly registering no route —
 * which looks exactly like a missing link in a menu and nothing like a mistake.
 *
 *   public static function pages(): array
 *   {
 *       return [
 *           'index'  => ListInvoices::class,
 *           'create' => CreateInvoice::class,
 *           'edit'   => EditInvoice::class,
 *           'view'   => ViewInvoice::class,
 *       ];
 *   }
 *
 * A value is usually just the component class. Where a page needs more — a
 * permission the others do not have, an extra middleware — it is a
 * {@see RoutePage} instead, which carries the same class plus that.
 *
 * The keys are the page *kinds* the router knows how to shape a URL for; see
 * {@see ResourceRoutes}. A key it does not know is
 * registered as a plain segment under the resource, so an application can add
 * `'archive' => ArchivedInvoices::class` without asking for anything.
 */
interface ProvidesResourcePages
{
    /**
     * @return array<string, class-string|RoutePage> Page kind => component class,
     *                                               or a {@see RoutePage} when that page
     *                                               needs a permission, middleware or a
     *                                               URI of its own.
     */
    public static function pages(): array;
}
