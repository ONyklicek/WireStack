<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin;

use NyonCode\WireCore\Core\Plugin\Contracts\IdentifiesHookTarget;

/**
 * Which component a hook payload came from.
 *
 * Payloads used to carry `object $table` and `?object $component` and nothing
 * else, so a callback meant for one module's list had to duck-type its way to an
 * answer — and every callback ran for every table in the application, which is
 * the same `if` written once per installed module, by hand, against untyped
 * objects. This is that answer, resolved once at the dispatch site.
 *
 * Three ways to name the same component, because a hook author has whichever one
 * their situation gives them:
 *
 * ```php
 * $manager->hook(Hook::TableConfiguring, $cb, for: 'invoices');        // registered key
 * $manager->hook(Hook::TableConfiguring, $cb, for: ListInvoices::class); // the host component
 * $manager->hook(Hook::FormSaving, $cb, for: Invoice::class);            // the model
 * ```
 *
 * A standalone component matches by class or model and has no key, which is not
 * a gap: it belongs to no registry entry, so there is nothing for a key to be.
 */
final readonly class HookTarget
{
    /**
     * @param  string  $surface  Which kind of surface this is: 'table', 'form', 'infolist', 'action'.
     * @param  object|null  $host  The component the surface renders in, when there is one.
     * @param  class-string|null  $model  The model the surface reads, when there is one.
     * @param  string|null  $key  The registered key the host declares, when it declares one.
     */
    public function __construct(
        public string $surface,
        public ?object $host = null,
        public ?string $model = null,
        public ?string $key = null,
    ) {}

    /**
     * Build a target from whatever the dispatch site holds.
     *
     * The key is asked of the host rather than passed in: only the host knows
     * which registry entry it shows, and asking here means every dispatch site
     * gets the answer without repeating the question.
     *
     * A model arrives as a class name or as an instance, because the two
     * dispatch sites that have one hold different halves: a table knows the
     * class it queries, a form holds the record it edits.
     *
     * @param  class-string|object|null  $model
     */
    public static function for(string $surface, mixed $host = null, string|object|null $model = null): self
    {
        $host = is_object($host) ? $host : null;

        return new self(
            surface: $surface,
            host: $host,
            model: is_object($model) ? $model::class : $model,
            key: $host instanceof IdentifiesHookTarget ? $host->hookKey() : null,
        );
    }

    /**
     * Whether a scope names this target.
     *
     * Deliberately one method with one string argument: a hook author writes the
     * name they have, and it is this that decides which of the three it is. The
     * host check is `instanceof` rather than an equality on the class name, so a
     * scope naming a base page class matches the pages that extend it — which is
     * how a package scopes a callback to every page it ships.
     */
    public function matches(string $scope): bool
    {
        if ($scope === '') {
            return false;
        }

        if ($this->key !== null && $scope === $this->key) {
            return true;
        }

        if ($this->model !== null && ($scope === $this->model || is_subclass_of($this->model, $scope))) {
            return true;
        }

        return $this->host !== null && $this->host instanceof $scope;
    }
}
