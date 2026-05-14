<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components\Display;

use Closure;
use NyonCode\WireCore\Foundation\Components\ViewComponent;

/**
 * Raw HTML display component with static helper factories.
 *
 * WARNING: Content is rendered unescaped. Never pass untrusted user input
 * directly to content(). Use the static factories (heading, paragraph)
 * which auto-escape text, or sanitize input before passing it.
 *
 * @phpstan-consistent-constructor
 */
class Html extends ViewComponent
{
    protected string|Closure|null $content = null;

    public function __construct(?string $name = null)
    {
        parent::__construct($name ?? 'html_'.uniqid());
    }

    public static function make(?string $name = null): static
    {
        return new static($name);
    }

    public function content(string|Closure|null $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->evaluate($this->content);
    }

    // ─── Static factories ───���──────────────────────────────────────

    public static function divider(): static
    {
        return static::make()->content('<hr class="my-4 border-gray-200 dark:border-gray-700">');
    }

    public static function spacer(string $size = '4'): static
    {
        return static::make()->content("<div class=\"h-{$size}\"></div>");
    }

    public static function heading(string $text, int $level = 2): static
    {
        $tag = "h{$level}";
        $classes = match ($level) {
            1 => 'text-2xl font-bold',
            2 => 'text-xl font-semibold',
            3 => 'text-lg font-medium',
            default => 'text-base font-medium',
        };

        return static::make()->content("<{$tag} class=\"{$classes} text-gray-900 dark:text-white\">".e($text)."</{$tag}>");
    }

    public static function paragraph(string $text): static
    {
        return static::make()->content('<p class="text-sm text-gray-600 dark:text-gray-400">'.e($text).'</p>');
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.html';
    }
}
