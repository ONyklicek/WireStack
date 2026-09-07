<?php

declare(strict_types=1);

namespace NyonCode\WireAdmin\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The mark at the top of the menu: `<x-wire-admin::brand />`.
 *
 * A shell with no way to show a logo is a shell nobody ships, and the previous
 * answer — "write your own layout" — asked an application to re-implement the
 * sidebar header to change one image. So the image is configuration and the
 * markup around it is not.
 *
 * **Three sources, in order: the slot, the config, the application name.** A
 * layout that passes its own markup wins outright; failing that, whatever
 * `wire-admin.brand` names is drawn; failing that, the application's initial in
 * a rounded square beside its name. There is no fourth case where the corner is
 * empty, because an empty corner reads as a half-built page rather than as a
 * deliberate absence.
 *
 * The wide menu and the collapsed rail take different images on purpose. A
 * wordmark scaled into 64 pixels is unreadable rather than small, so the rail
 * asks for `mark` and falls back to the initial when an application gave only a
 * logo — which is the common case, and the reason the fallback is not an error.
 */
class Brand extends Component
{
    /*
     * No `rail` argument. Both forms are rendered and CSS picks between them:
     * a component that took the state as a prop would have to be re-rendered
     * every time the menu folds, and the logo would arrive a frame after the
     * rest of the header — in the one corner of the page where that is
     * unmissable.
     */

    public function name(): string
    {
        $configured = config('wire-admin.brand.name');

        return is_string($configured) && $configured !== ''
            ? $configured
            : (string) config('app.name', 'Wire');
    }

    /**
     * The first letter of the name, for the square drawn when there is no image.
     *
     * `mb_substr` rather than `[0]`: an application called "Účetnictví" would
     * otherwise get the first *byte* of a two-byte letter, which renders as a
     * replacement character in the one place a brand cannot afford one.
     */
    public function initial(): string
    {
        return mb_strtoupper(mb_substr($this->name(), 0, 1));
    }

    public function logo(): ?string
    {
        return $this->asset(config('wire-admin.brand.logo'));
    }

    /**
     * The image used while the page is dark, when one was given.
     *
     * Null is not a fallback to the light logo here — the view draws whichever
     * exists and hides the other with `dark:` classes, so an application with a
     * single logo that works on both grounds configures exactly one key.
     */
    public function logoDark(): ?string
    {
        return $this->asset(config('wire-admin.brand.logo_dark'));
    }

    public function mark(): ?string
    {
        return $this->asset(config('wire-admin.brand.mark'));
    }

    public function height(): int
    {
        $height = config('wire-admin.brand.height', 28);

        return is_numeric($height) ? max(1, (int) $height) : 28;
    }

    public function url(): string
    {
        $url = config('wire-admin.brand.url');

        return is_string($url) && $url !== '' ? $url : '/';
    }

    /**
     * Resolve a configured path to something an `<img src>` can use.
     *
     * A path already carrying a scheme — `https:`, and `data:` for an inlined
     * SVG — a protocol-relative URL, or a leading slash is passed through
     * untouched. Those are the shapes that mean "I have already decided where
     * this lives", and running `asset()` over them would prefix a host onto a
     * URL that was already complete.
     */
    protected function asset(mixed $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        return preg_match('#^(?:[a-z][a-z0-9+.-]*:|//|/)#i', $path) === 1
            ? $path
            : asset($path);
    }

    public function render(): View
    {
        return view('wire-admin::brand');
    }
}
