<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Enums;

/**
 * What a person chose to look at: light, dark, or whatever the system says.
 *
 * Three states, not a boolean, and the third is the one that carries its weight.
 * A two-way toggle forces a choice the moment somebody touches it and then keeps
 * it for ever — so a laptop that dims itself in the evening stops being followed
 * the first time anybody presses the button. **`System` is a choice too**, and it
 * has to be one you can go back to.
 *
 * The vocabulary lives here rather than in the shell's Blade file because it is
 * three things a view would otherwise hold as a `match`: the stored value, the
 * label, and the icon. One owner means a second surface — another shell, a
 * settings page, the docs site — renders the same three choices without
 * re-deciding what they are called.
 *
 * The *applying* of it stays in the browser, in the small script the layout puts
 * in its head: reading the stored value from PHP would mean rendering the page
 * before knowing which colours it wants, and that flash is the one thing every
 * dark-mode implementation is judged by.
 */
enum Theme: string
{
    case Light = 'light';

    /** Follow the operating system, and keep following it as it changes. */
    case System = 'system';

    case Dark = 'dark';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Anything unknown — an old stored value, a hand-edited setting — is System. */
    public static function resolve(string|self|null $theme): self
    {
        if ($theme instanceof self) {
            return $theme;
        }

        return self::tryFrom((string) $theme) ?? self::System;
    }

    public function label(): string
    {
        return __('wire-core::messages.theme_'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Light => 'outline:sun',
            self::System => 'outline:computer-desktop',
            self::Dark => 'outline:moon',
        };
    }
}
