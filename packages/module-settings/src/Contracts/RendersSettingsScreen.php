<?php

declare(strict_types=1);

namespace NyonCode\WireModuleSettings\Contracts;

use Livewire\Component;
use NyonCode\WireModuleSettings\Support\Settings;

/**
 * A settings group whose screen is a Livewire component of its own.
 *
 * The way out for a screen a form cannot describe — a calculator with its own
 * state, a page that tests a connection before it saves. The module still owns
 * everything around it: the group's place in the switcher, its heading, its URL
 * and who may open it ({@see GuardsSettingsGroup}). The component owns what is
 * inside and saves through {@see Settings}
 * like any other code.
 *
 *   public static function component(): string
 *   {
 *       return WageSettingsScreen::class;
 *   }
 *
 * It is mounted with the group's name as `group`. `schema()` is still part of
 * the contract and may simply return `[]`.
 */
interface RendersSettingsScreen
{
    /**
     * The Livewire component drawn in place of the form.
     *
     * @return class-string<Component>
     */
    public static function component(): string;
}
