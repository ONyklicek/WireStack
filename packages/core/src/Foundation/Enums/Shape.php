<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Enums;

/**
 * How the corners are cut.
 *
 * Two settings, and unlike {@see Density} this one is the application's alone.
 * Density is a working preference — one person likes more rows on screen than
 * another, and the switch beside the theme toggle is theirs. Shape is identity:
 * an admin that is round for one colleague and square for the next is not a
 * preference being respected, it is two products.
 *
 * **Sharp is two rules, not one**, and the second one is why this ships from the
 * framework rather than being a line an application writes:
 *
 *   1. The radius tokens go to zero. Tailwind 4 compiles every `rounded-*` step
 *      to `var(--radius-*)`, so 34 corners on a users page go square at once —
 *      cards, inputs, buttons, the column toggle.
 *   2. **Pills do not follow, and are squared by name.** `rounded-full` compiles
 *      to `calc(infinity * 1px)` and reads no token at all, so 20 pills survive
 *      step 1 untouched. Badges and tags are content and are squared; **avatars
 *      are deliberately left round**. A sharp theme that squares the faces reads
 *      as broken rather than sharp, and no token can tell "round because it is a
 *      card" from "round because it is a face" — that distinction is why the
 *      `data-wire` hooks exist.
 *
 * The rules key on `[data-shape]` for the same reason density's do: one place
 * decides, and the attribute is where a stylesheet and a config meet.
 */
enum Shape: string
{
    /** The shipped corners. Nothing is emitted for it. */
    case Rounded = 'rounded';

    case Sharp = 'sharp';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Anything unknown — a typo, an old value — is the shipped shape. */
    public static function resolve(string|self|null $shape): self
    {
        if ($shape instanceof self) {
            return $shape;
        }

        return self::tryFrom((string) $shape) ?? self::Rounded;
    }

    /** What `config('wire-core.shape')` asks for. */
    public static function configured(): self
    {
        return self::resolve(config('wire-core.shape'));
    }

    public function label(): string
    {
        return __('wire-core::messages.shape_'.$this->value);
    }
}
