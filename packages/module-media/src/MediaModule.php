<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia;

use NyonCode\WireCore\Core\Modules\Module;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use NyonCode\WireModuleMedia\Resources\MediaResource;

/** What this package contributes: the file library. */
class MediaModule extends Module
{
    public function getId(): string
    {
        return 'media';
    }

    public function resources(): array
    {
        return [MediaResource::class];
    }

    public function navigation(): ?NavigationGroup
    {
        $group = NavigationGroup::make((string) config('wire-module-media.navigation.group', 'content'))
            ->icon('outline:rectangle-stack')
            ->sort((int) config('wire-module-media.navigation.sort', 80));

        $label = config('wire-module-media.navigation.label');

        // A closure, and that is not style: `navigation()` is called while
        // core spreads modules into the registries, which is **before** this
        // package's own provider has registered its translations. A `__()`
        // evaluated there misses, and the translator caches the miss for the
        // whole request — so every later lookup in this namespace answers
        // with the key. Resolved at render, it is simply right.
        return is_string($label) && $label !== ''
            ? $group->label($label)
            : $group->label(fn (): string => __('wire-module-media::messages.content'));
    }
}
