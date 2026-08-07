<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use Illuminate\Contracts\Support\Htmlable;
use NyonCode\WireCore\Exceptions\SkeletonSlotException;

/**
 * A view rendered once, with holes left where the per-record values go.
 *
 * This is the canonical owner of the render-cost model's central move (see
 * AI_CODING_STANDARD.md, "the render-cost model is binding"): a component renders
 * itself ONCE into reusable markup, and each row splices its own state into it —
 * instead of `view()->render()` per cell, which is `N×View` and the dominant cost
 * of the whole engine.
 *
 * It was a private string constant and a `str_replace` inside `Column` before this;
 * promoting it buys the thing that actually matters, which is MORE THAN ONE HOLE.
 * A single token could only carry the cell's content, so a column with a per-record
 * url, copy value or description-closure fell back to the per-cell render and cost
 * 18–33× as much. Several named slots carry all of them.
 *
 * ## Encoding is the caller's job, and it is deliberate
 *
 * A slot is a hole in *already-rendered* markup, so the value going in must arrive
 * encoded for the position it lands in — `e($url)` inside an attribute, raw HTML for
 * an icon. The sentinel is built from characters `htmlspecialchars()` leaves alone,
 * so a template that escapes its own inputs (most do) does not mangle the sentinel
 * at compile time, and the caller re-applies exactly the encoding the template would
 * have applied.
 *
 * The rule this buys, and the reason it is safe: **one slot, one position, one
 * encoding.** A value that appears twice under two encodings needs two slots. That
 * is the boundary where this stops being cheap — see the inline-edit evaluation in
 * architecture/plans/render-engine-htmlable-first.md.
 *
 * ## Structure vs. value
 *
 * A slot substitutes a *value*, never a shape. When a record changes the markup's
 * structure — a url present on one row and absent on the next — that is a different
 * skeleton, and the caller caches one per shape. Cheap, because shapes are few:
 * O(shapes) renders, not O(rows).
 */
final class Skeleton implements Htmlable
{
    /**
     * @param  list<string>  $slots  the slot names this template actually contains
     */
    private function __construct(
        private readonly string $template,
        private readonly array $slots,
    ) {}

    /**
     * The placeholder to hand a view in place of a per-record value.
     *
     * Deliberately built from `ᐊ` and an arbitrary hex tag rather than something
     * like `%url%`: it has to survive `htmlspecialchars()` unchanged (so no
     * `& < > " '`), and it has to be a string no real content plausibly holds,
     * because a sentinel that occurs in the data would be substituted as if it
     * were a hole.
     */
    public static function slot(string $name): string
    {
        return "ᐊWIRE_SLOT_{$name}_a3f9e1ᐊ";
    }

    /**
     * Compile rendered markup into a reusable skeleton.
     *
     * Only slots that are actually present are recorded, so a shape that omits one
     * (no url on this row) does not demand a value for it at fill time.
     */
    public static function compile(string $html, string ...$slots): self
    {
        $present = [];

        foreach ($slots as $name) {
            if (str_contains($html, self::slot($name))) {
                $present[] = $name;
            }
        }

        return new self(trim($html), $present);
    }

    /**
     * Splice one record's values in.
     *
     * A single `strtr()` pass, which matters twice over: it is one scan for all
     * slots rather than one per slot, and — the correctness half — it **does not
     * re-examine what it just substituted**. So a value that happens to contain
     * another slot's sentinel is left alone instead of being substituted again,
     * which sequential `str_replace` calls would get wrong.
     *
     * @param  array<string, string>  $values  keyed by slot name, already encoded
     *                                         for the position the slot sits in
     *
     * @throws SkeletonSlotException when a slot in the template has no value
     */
    public function fill(array $values): string
    {
        if ($this->slots === []) {
            return $this->template;
        }

        $pairs = [];

        foreach ($this->slots as $name) {
            if (! array_key_exists($name, $values)) {
                throw SkeletonSlotException::missing($name);
            }

            $pairs[self::slot($name)] = $values[$name];
        }

        return strtr($this->template, $pairs);
    }

    /**
     * The compiled chrome, slots and all.
     *
     * This is the *unfilled* template — the shared markup, not a rendered cell. It
     * is here because a skeleton is the Htmlable half of "a component renders
     * itself"; rendering a row means {@see fill()}.
     */
    public function toHtml(): string
    {
        return $this->template;
    }
}
