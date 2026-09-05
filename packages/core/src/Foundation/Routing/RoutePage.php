<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Routing;

use NyonCode\WireCore\Foundation\Concerns\HasAuthorization;

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
 */
final class RoutePage
{
    /** @var array<int, string> */
    private array $middleware = [];

    private ?string $permission = null;

    private ?string $uri = null;

    /**
     * @param  class-string  $component  The Livewire page component.
     */
    private function __construct(public readonly string $component) {}

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
