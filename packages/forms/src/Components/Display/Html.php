<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components\Display;

use Closure;

/**
 * Raw HTML display component with static helper factories.
 *
 * WARNING: Content is rendered unescaped. Never pass untrusted user input
 * directly to content(). Use the static factories (heading, paragraph)
 * which auto-escape text, or sanitize input before passing it.
 *
 * @phpstan-consistent-constructor
 */
class Html extends Display
{
    public function __construct(?string $name = null)
    {
        parent::__construct($name ?? 'html_'.uniqid());
    }

    public static function make(?string $name = null): static
    {
        return new static($name);
    }

    // ─── Static factories ───���──────────────────────────────────────

    public static function divider(): static
    {
        return static::make()->content(static::partial('divider'));
    }

    public static function spacer(string $size = '4'): static
    {
        return static::make()->content(static::partial('spacer', ['size' => $size]));
    }

    public static function heading(string $text, int $level = 2): static
    {
        return static::make()->content(static::partial('heading', [
            'tag' => "h{$level}",
            'classes' => match ($level) {
                1 => 'text-2xl font-bold',
                2 => 'text-xl font-semibold',
                3 => 'text-lg font-medium',
                default => 'text-base font-medium',
            },
            'text' => $text,
        ]));
    }

    public static function paragraph(string $text): static
    {
        return static::make()->content(static::partial('paragraph', ['text' => $text]));
    }

    /**
     * One factory's markup, as a closure the component resolves at render time.
     *
     * The factories set `content()` like any caller would, but what they set is
     * framework markup rather than the consumer's own — so it comes out of a
     * Blade partial ({@see AI_CODING_STANDARD.md}, "always Htmlable, always
     * Blade"), which is also the vendor:publish override point for it.
     *
     * A closure rather than a rendered string because `content()` already accepts
     * one: a schema is built on every request and these factories sit in it, so
     * rendering eagerly would pay for the markup of a field that is never shown.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function partial(string $name, array $data = []): Closure
    {
        // Trimmed, so a partial's own trailing newline does not become a text
        // node the concatenated strings never emitted.
        return fn (): string => trim(view("wire-forms::components.html.{$name}", $data)->render());
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.html';
    }
}
