<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Routing;

use NyonCode\WireCore\Foundation\Concerns\HasAuthorization;
use NyonCode\WireCore\Foundation\Concerns\HasIcon;
use NyonCode\WireCore\Foundation\Concerns\HasLabel;
use NyonCode\WireCore\Foundation\Concerns\HasName;
use NyonCode\WireCore\Foundation\Concerns\HasSortOrder;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;

/**
 * One page of a registered thing, with what its route needs beyond the component.
 *
 * Lives here rather than with the router that reads it (ADR 0026): a `Dashboard`
 * is `Widgets/` and cannot see `wire-panels`, so a page declaration reachable
 * only from the top package would have made "which components render me" a
 * question only a resource is allowed to answer. The URL *convention* is still
 * the panel layer's — `ResourceRoutes` owns the shape and the route names; this
 * owns only what a declaration carries.
 *
 * `pages()` may name a component and nothing else — that is the common case and
 * stays a one-liner. This is for the page that differs: an edit screen behind a
 * permission the list is not, a destructive page behind an extra middleware.
 *
 *   'edit' => RoutePage::make(EditInvoice::class)->permission('invoices.update'),
 *   'archive' => RoutePage::make(ArchivedInvoices::class)->middleware(['signed']),
 *
 * `permission()` is the same word the rest of the framework uses for this
 * ({@see HasAuthorization}) and it lands
 * on the route as Laravel's own `can:` middleware, so Gate, spatie/laravel-permission
 * and permission-extended all keep working exactly as they do everywhere else.
 * Nothing here re-implements an authorization check.
 *
 * ## What a page is called, and in what order
 *
 * A route needs none of that. A **record's sub-navigation** does: it draws the
 * pages of one record as tabs, and a tab is a word and usually an icon.
 *
 *   'edit' => RoutePage::make(EditInvoice::class)->label(__('Edit'))->icon('outline:pencil')->sort(20),
 *
 * All three come from the canonical concerns rather than from properties of this
 * class's own — `HasLabel`, `HasIcon`, `HasSortOrder` — which is deliberately
 * *not* what `permission()` above did. That one predates the rule and restates a
 * word `HasAuthorization` already owns; repeating the pattern for three more
 * would be four vocabularies in one class.
 *
 * Declaring none of it is the common case and stays a one-liner. A page that
 * names no label is named by whoever reads it — the sub-navigation translates
 * the four known kinds and falls back to a humanised key — because the array key
 * a page is declared under (`'edit'`) is not something this object is given.
 */
final class RoutePage
{
    use EvaluatesClosures;
    use HasIcon;
    use HasLabel;
    use HasName;
    use HasSortOrder;

    /** @var array<int, string> */
    private array $middleware = [];

    private ?string $permission = null;

    private ?string $uri = null;

    /**
     * @param  class-string  $component  The Livewire page component.
     */
    private function __construct(public readonly string $component)
    {
        // `HasName` declares the property without a default, and `HasLabel`
        // falls back to a humanised version of it — so an unnamed page would
        // fatal on the first `getLabel()` rather than answer "nothing declared".
        // Empty is that answer: a page is named by the array key it is declared
        // under, which this object never sees.
        $this->name = '';
    }

    /**
     * @param  class-string  $component
     */
    public static function make(string $component): self
    {
        return new self($component);
    }

    /**
     * @param  array<int, string>|string  $middleware
     */
    public function middleware(array|string $middleware): self
    {
        $this->middleware = array_merge($this->middleware, (array) $middleware);

        return $this;
    }

    /**
     * A Gate ability or permission string this page requires.
     *
     * Applied as `can:{permission}` — the framework does not check it here,
     * Laravel's authorization middleware does, so the answer is the same one
     * every other surface gets.
     */
    public function permission(?string $permission): self
    {
        $this->permission = $permission;

        return $this;
    }

    /** Override the URI segment this page sits at, relative to the resource. */
    public function uri(?string $uri): self
    {
        $this->uri = $uri;

        return $this;
    }

    public function getUri(): ?string
    {
        return $this->uri;
    }

    /**
     * The ability this page requires, before it becomes middleware.
     *
     * The route reads {@see getMiddleware()}; a *screen* needs the ability
     * itself, so a button that leads here can be hidden by the same rule the
     * route enforces. Without it a list either shows an Edit button that lands
     * on a 403, or repeats the permission string next to the one declared here
     * and drifts from it.
     */
    public function getPermission(): ?string
    {
        return $this->permission;
    }

    /**
     * Every middleware this page's route carries, permission included.
     *
     * @return array<int, string>
     */
    public function getMiddleware(): array
    {
        return $this->permission === null
            ? $this->middleware
            : [...$this->middleware, 'can:'.$this->permission];
    }
}
