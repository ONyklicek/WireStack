<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Hooks;

use NyonCode\WireCore\Core\Plugin\Contracts\HasHookTarget;
use NyonCode\WireCore\Core\Plugin\HookTarget;

/**
 * Typed payload for the 'page.mounting' hook.
 *
 * Dispatched once per mount, from the one trait every resource page composes —
 * and **last**, which is a measurement rather than a preference: Livewire calls
 * a component's own `mount()` before the `mount{Trait}` hooks, so by the time
 * this runs the edit page has resolved its record and seeded its form. A hook
 * that fired first would see neither.
 *
 * That makes `$page` the mutable half, and the only one. It is the mounted
 * component, so a callback reaches whatever the page makes public:
 *
 * ```php
 * $payload->page->data['team_id'] = auth()->user()->team_id;   // seed a form
 * ```
 *
 * **Public, and that word is load-bearing.** A page mounts once and then answers
 * Livewire updates from its snapshot, which carries public properties and nothing
 * else — so state written to a protected property here is correct on the first
 * paint and gone on every one after. That is why `$title` is **readonly**: a page's
 * `$title` is protected by design (subclasses declare it), so a hook that set it
 * would be offering exactly that footgun. It is here to be read — what the page
 * will call itself, at the moment the callback runs.
 *
 * Typed only.
 */
final class PageMountingPayload implements HasHookTarget
{
    /**
     * @param  object  $page  The page component, fully mounted
     * @param  string|null  $title  What the page will call itself, derived or declared
     * @param  string|null  $zone  The zone the page was opened in, when there is one
     * @param  HookTarget|null  $target  Which component this came from, for scoped callbacks
     */
    public function __construct(
        public readonly object $page,
        public readonly ?string $title = null,
        public readonly ?string $zone = null,
        public readonly ?HookTarget $target = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'page' => $this->page,
            'title' => $this->title,
            'zone' => $this->zone,
        ];
    }

    public function hookTarget(): ?HookTarget
    {
        return $this->target;
    }
}
