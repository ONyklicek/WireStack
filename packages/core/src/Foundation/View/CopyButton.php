<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use NyonCode\WireCore\Foundation\Icons\IconManager;

/**
 * The canonical copy-to-clipboard affordance.
 *
 * There were two of these. A table's copyable cell had the good one — a plain
 * `<button data-copy>` plus one document listener for the whole page — while an
 * infolist's copyable entry still carried an Alpine component per entry, with its
 * own `copied` flag, its own timeout and two icons toggled by `x-show`. Same
 * capability, two implementations, and the worse one in the lower package.
 *
 * So the owner lives here, in the layer both can reach ({@see CLAUDE.md}: a
 * capability shared across packages belongs to the lowest layer that can own it),
 * and it owns all three parts — the markup ({@see self::render()}), the behaviour
 * (`copy.js`, registered as the `wire-core::copy` bundle) and the feedback pill
 * (`wire-core::partials.copy-assets`).
 *
 * Rendered ONCE PER SHAPE and spliced per value, like every other per-row surface
 * in the engine: the value and the announcement are the only things that vary
 * between two copy buttons of the same kind, and both land in one attribute under
 * one encoding.
 */
class CopyButton
{
    /** Default button chrome: invisible until the row or entry is hovered. */
    public const DEFAULT_CLASS = 'opacity-0 group-hover:opacity-100 transition-opacity p-0.5 rounded hover:bg-gray-100 dark:hover:bg-gray-700';

    /** @var array<string, Skeleton> one compiled button per shape */
    private array $skeletons = [];

    /**
     * One copy button.
     *
     * Everything except `$value` and `$message` is the shape: two calls that agree
     * on the chrome share a compiled skeleton and cost only a splice.
     */
    public function render(
        string $value,
        ?string $message = null,
        ?string $label = null,
        ?string $title = null,
        ?string $class = null,
        string $testId = 'copy',
        ?string $icon = null,
    ): string {
        $title ??= __('wire-core::messages.copy');
        $label ??= $message ?? $title;
        $class ??= self::DEFAULT_CLASS;
        $icon ??= app(IconManager::class)->render(
            'clipboard-document',
            'w-4 h-4',
            'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300',
        );

        $shape = md5($label."\0".$title."\0".$class."\0".$testId."\0".$icon);

        $skeleton = $this->skeletons[$shape] ??= Skeleton::compile(
            view('wire-core::partials.copy-button', [
                'label' => $label,
                'title' => $title,
                'class' => $class,
                'testId' => $testId,
                'icon' => $icon,
                'value' => Skeleton::slot('value'),
                'message' => Skeleton::slot('message'),
            ])->render(),
            'value',
            'message',
        );

        return $skeleton->fill([
            'value' => e($value),
            'message' => e((string) $message),
        ]);
    }
}
