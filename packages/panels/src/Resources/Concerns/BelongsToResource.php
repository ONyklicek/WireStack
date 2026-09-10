<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use NyonCode\WireCore\Core\Plugin\HookDispatch;
use NyonCode\WireCore\Core\Plugin\Hooks\PageMountingPayload;
use NyonCode\WireCore\Core\Plugin\HookTarget;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Foundation\Enums\Hook;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;
use NyonCode\WireCore\Foundation\Routing\RoutePage;
use NyonCode\WireCore\Foundation\Routing\Zone;
use NyonCode\WirePanels\Exceptions\ResourcePageException;

/**
 * The half of a page that is about *which* resource it shows.
 *
 * Every page asks the same three questions — is a resource declared, is it
 * really a resource, does it have the surface I need — and answering them once
 * per page is how four pages end up disagreeing about what a missing surface
 * means. The surface-specific half stays on each page, because that is the part
 * that genuinely differs.
 */
trait BelongsToResource
{
    /**
     * Optional heading. Each page decides what it falls back to, because a list
     * wants the plural and a form wants the singular.
     */
    protected ?string $title = null;

    /**
     * The zone this page was opened in, read once and carried.
     *
     * Public because it has to survive the round trip, and read in a mount hook
     * because that is the only moment it can be read at all: during a Livewire
     * update `Route::currentRouteName()` is `livewire.update`, so a breadcrumb
     * that re-derived it would link out of the zone the user is in — correctly
     * on the first paint and wrongly on every one after (ADR 0027).
     */
    public ?string $breadcrumbZone = null;

    /** Livewire calls this for the trait, on mount, after the page's own. */
    public function mountBelongsToResource(): void
    {
        $this->breadcrumbZone = Zone::current();

        $this->mountedThroughPlugins();
    }

    /**
     * Let anything installed have a say once the page has mounted.
     *
     * One dispatch site for four pages, because this is the one thing all four
     * compose — and it runs **last**, which is a measurement rather than a
     * preference: Livewire calls a component's own `mount()` before the
     * `mount{Trait}` hooks (`SupportLifecycleHooks`), so by the time this runs the
     * edit page has resolved its record and seeded its form. A hook dispatched
     * from `mount()` would see neither, and the callback that wanted to add a key
     * to the state bag would be overwritten by the seed that followed it.
     *
     * The page itself is the whole of what a callback changes, and the payload
     * says so: it is mounted, so whatever the page makes **public** is reachable
     * and survives the round trip. Nothing is written back from the payload,
     * because the one thing that looked writable — the heading — is a protected
     * property here, and Livewire's snapshot carries public ones only. A hook
     * that set it would have been right on the first paint and wrong on every
     * update after, which is the failure `$breadcrumbZone` above exists to avoid.
     *
     * A dashboard page composes none of this and is deliberately not covered: it
     * shows no resource, so there would be no key to scope a callback by. Its
     * widgets are addressable through `widget.configuring` instead.
     */
    private function mountedThroughPlugins(): void
    {
        HookDispatch::typed(Hook::PageMounting, fn () => new PageMountingPayload(
            page: $this,
            title: $this->getTitle(),
            zone: $this->breadcrumbZone,
            target: HookTarget::for('page', $this),
        ));
    }

    /**
     * Where this page sits: the resource's list, then the page itself.
     *
     * Two crumbs at most, because that is the whole depth these pages have — a
     * list is inside nothing, and an edit page is inside its list. A trail of one
     * renders nothing, so a list page pays for none of this.
     *
     * @return array<int, NavigationItem>
     */
    public function breadcrumbs(): array
    {
        $resource = static::$resource;

        if ($resource === null || ! in_array(DescribesResource::class, class_implements($resource) ?: [], true)) {
            return [];
        }

        $crumbs = [
            NavigationItem::make($resource::pluralLabel())->url(
                app(ResolvesPageUrls::class)->urlFor($resource::key(), 'index', [], $this->breadcrumbZone),
            ),
        ];

        $title = $this->getTitle();

        if ($title !== null && $title !== $resource::pluralLabel()) {
            $crumbs[] = NavigationItem::make($title);
        }

        return $crumbs;
    }

    /**
     * Where one of this resource's pages is, in the zone this page was opened in.
     *
     * The zone is the whole reason this cannot live on the resource: the same
     * resource mounted in two zones has two different edit URLs, and a resource
     * has no way to know which one it is being drawn in. The page does — it read
     * it in `mount()` and kept it, because during a Livewire update
     * `Route::currentRouteName()` is `livewire.update` and re-deriving would give
     * a row action the right link on the first paint and a null on every one
     * after (ADR 0027). A table re-renders on every search keystroke.
     *
     * Null is a real answer: a resource that declares no such page, or an
     * application that routes none, gets a list with no link rather than one
     * with a broken link.
     *
     * @param  string  $page  A page kind — `index`, `create`, `view`, `edit`, or one of the resource's own.
     */
    protected function pageUrl(string $page, mixed $record = null): ?string
    {
        $resource = static::$resource;

        if ($resource === null || ! in_array(DescribesResource::class, class_implements($resource) ?: [], true)) {
            return null;
        }

        $parameters = $record instanceof Model
            ? ['record' => $record->getKey()]
            : [];

        return app(ResolvesPageUrls::class)->urlFor($resource::key(), $page, $parameters, $this->breadcrumbZone);
    }

    /**
     * The same URL as {@see pageUrl()}, minus the pages this user cannot open.
     *
     * `ResourceRoutes` turns a page's declared permission into `can:` middleware
     * on its route, so sending someone to a page they lack it for is a 403 —
     * strictly worse than the page they were already looking at. Asking first is
     * what lets a caller fall through to its next candidate instead, which is
     * how a create lands on the list when it may not open the record it just
     * made.
     *
     * Null for the same reasons `pageUrl()` gives one, plus this one; a caller
     * that wants the URL regardless of who is asking keeps using `pageUrl()`.
     *
     * @param  string  $page  A page kind — `index`, `create`, `view`, `edit`, or one of the resource's own.
     */
    protected function reachablePageUrl(string $page, mixed $record = null): ?string
    {
        $url = $this->pageUrl($page, $record);

        if ($url === null) {
            return null;
        }

        $permission = $this->pagePermission($page);

        // Gate rather than a permission package's own API, like every other
        // authorization check in the framework: both Spatie and
        // permission-extended register into it, and a page declaring nothing is
        // open to whoever the route already let through.
        return $permission === null || Gate::allows($permission) ? $url : null;
    }

    /**
     * The ability one of this resource's pages requires, as the resource declared it.
     *
     * Derived rather than restated, and that is the point: `ResourceRoutes` turns
     * the same declaration into `can:` middleware on the route, so a button
     * hidden by this and a route guarded by that cannot disagree. A page declared
     * as a bare class string requires nothing, and neither does the button.
     */
    protected function pagePermission(string $page): ?string
    {
        $resource = static::$resource;

        // `is_a()` with a class *string* rather than the `class_implements()`
        // shape the crumb trail above uses: the two are equivalent at runtime,
        // and only this one narrows the type, so `pages()` is a call static
        // analysis can see is declared.
        if ($resource === null || ! is_a($resource, ProvidesPages::class, true)) {
            return null;
        }

        $declared = $resource::pages()[$page] ?? null;

        return $declared instanceof RoutePage ? $declared->getPermission() : null;
    }

    /** Every page has one; the trail's last crumb is it. */
    abstract public function getTitle(): ?string;

    /** @return class-string<DescribesResource>|null */
    public static function resourceClass(): ?string
    {
        return static::$resource;
    }

    /**
     * The registered key a plugin hook can address this page's surfaces by.
     *
     * This is what turns `hook(Hook::TableConfiguring, $cb, for: 'invoices')`
     * into something an application can actually write: the table and the form
     * on this page are built inside code the application may not own — a module
     * shipped as a package — so the only handle it has on them is the key that
     * module registered.
     *
     * Deliberately tolerant of a page that declares nothing. A page mid-write,
     * or one that renders its own table with no resource at all, is scoped by
     * class instead; throwing here would turn a hook's *absence* of scope into a
     * render failure.
     */
    public function hookKey(): ?string
    {
        $resource = static::$resource;

        if ($resource === null || ! in_array(DescribesResource::class, class_implements($resource) ?: [], true)) {
            return null;
        }

        return $resource::key();
    }

    /**
     * The declared resource, checked.
     *
     * @param  class-string  $surface  The contract this page needs it to implement.
     *
     * @throws ResourcePageException When nothing is declared, it is not a
     *                               resource, or it lacks the surface — each of which would otherwise
     *                               render as an empty page, and empty reads as "nothing here" rather
     *                               than as a mistake.
     */
    protected function requireResource(string $surface): object
    {
        $resource = static::$resource;

        if ($resource === null) {
            throw ResourcePageException::noSource(static::class, $surface);
        }

        if (! in_array(DescribesResource::class, class_implements($resource) ?: [], true)) {
            throw ResourcePageException::notAResource(static::class, $resource);
        }

        if (! is_subclass_of($resource, $surface)) {
            throw ResourcePageException::resourceLacksSurface(static::class, $resource, $surface);
        }

        // Through the container, so a resource may type-hint its own dependencies.
        return app($resource);
    }

    /** The resource's singular label, or null when no resource is declared. */
    protected function resourceLabel(): ?string
    {
        $resource = static::$resource;

        return $resource !== null ? $resource::label() : null;
    }
}
