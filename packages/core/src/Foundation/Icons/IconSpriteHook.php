<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Icons;

use Livewire\Component;
use Livewire\Features\SupportScriptsAndAssets\SupportScriptsAndAssets;
use NyonCode\WireCore\Foundation\Support\PartialRenderHook;

use function Livewire\on;
use function Livewire\store;

/**
 * Makes every Livewire render a sprite scope ({@see IconSprite}).
 *
 * A component's render and an island's render are the two pieces of markup
 * Livewire sends whole; a partial a write answers with is the third, scoped by
 * {@see PartialRenderHook}. Each closes
 * with the symbols it references embedded, so the piece is correct on its own
 * whichever of them the browser morphs in.
 *
 * The browser half rides as a Livewire asset of the first component that
 * renders a reference — head-injected on a page load, sent in the payload of an
 * update, once per page either way — so a layout needs no directive for it.
 */
final class IconSpriteHook
{
    private const ASSET_KEY = 'wire-core-icon-sprite';

    public static function register(): void
    {
        on('render', static fn ($component) => self::scope($component));
        on('renderIsland', static fn ($component) => self::scope($component));
    }

    /**
     * @return (callable(string): string)|null
     */
    private static function scope(mixed $component): ?callable
    {
        if (! config('wire-core.icons.sprite', false)) {
            return null;
        }

        $sprite = app(IconSprite::class);
        $sprite->open();

        return static function ($html) use ($sprite, $component): string {
            $html = $sprite->close((string) $html);

            if ($component instanceof Component && IconSprite::references($html)) {
                self::pushAsset($component);
            }

            return $html;
        };
    }

    /**
     * The same bookkeeping `@assets … @endassets` compiles to, written out here
     * because no view can own the include: it belongs to the icons, which are
     * everywhere.
     */
    private static function pushAsset(Component $component): void
    {
        if (in_array(self::ASSET_KEY, SupportScriptsAndAssets::$alreadyRunAssetKeys, true)
            || ! app('router')->has('wire-core.asset')) {
            return;
        }

        SupportScriptsAndAssets::$alreadyRunAssetKeys[] = self::ASSET_KEY;

        store($component)->push(
            'assets',
            view('wire-core::partials.icon-sprite-assets')->render(),
            self::ASSET_KEY,
        );
    }
}
