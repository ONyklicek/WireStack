<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use NyonCode\WireCore\Foundation\Concerns\HasDefault;

/**
 * TipTap rich text editor with a fully configurable toolbar and extension system.
 *
 * The editor JS ships pre-bundled inside this package and is injected
 * automatically by the field's Blade view, so no npm install, build step, or
 * manual import is required. See docs/forms/fields/tiptap-editor.md.
 */
class TiptapEditor extends Field
{
    /** @var array<int, string> */
    protected array $toolbarButtons = self::DEFAULT_TOOLBAR;

    /** @var array<int, string> */
    protected array $disabledToolbarButtons = [];

    protected string $outputFormat = 'html';

    protected int $minHeight = 240;

    protected ?int $maxLength = null;

    protected bool $withImages = false;

    protected bool $withTables = false;

    protected bool $withTextAlign = false;

    protected bool $withHighlight = false;

    /** Default toolbar shown when no override is given. */
    public const DEFAULT_TOOLBAR = [
        'bold', 'italic', 'underline', 'strike',
        '|',
        'h1', 'h2', 'h3',
        '|',
        'bulletList', 'orderedList',
        '|',
        'link', 'blockquote', 'codeBlock',
        '|',
        'undo', 'redo',
    ];

    // ─── Toolbar ───────────────────────────────────────────────────

    /**
     * Set the toolbar button layout.
     *
     * @param  array<int, string>  $buttons  Button keys + '|' separators.
     */
    public function toolbarButtons(array $buttons): static
    {
        $this->toolbarButtons = $buttons;

        return $this;
    }

    /**
     * Remove specific buttons from the default toolbar.
     *
     * @param  array<int, string>  $buttons  Keys to remove from the toolbar.
     */
    public function disableToolbarButtons(array $buttons): static
    {
        $this->disabledToolbarButtons = $buttons;

        return $this;
    }

    /** Hide the toolbar entirely. */
    public function disableAllToolbarButtons(): static
    {
        $this->toolbarButtons = [];

        return $this;
    }

    // ─── Output ────────────────────────────────────────────────────

    /** Store the content as HTML (default). */
    public function outputHtml(): static
    {
        $this->outputFormat = 'html';

        return $this;
    }

    /** Store the content as a TipTap JSON document string. */
    public function outputJson(): static
    {
        $this->outputFormat = 'json';

        return $this;
    }

    // ─── Extensions ────────────────────────────────────────────────

    /** Enable image support and add an image button to the toolbar. */
    public function withImages(bool $condition = true): static
    {
        $this->withImages = $condition;

        if ($condition) {
            $this->toolbarButtons = array_merge($this->toolbarButtons, ['|', 'image']);
        }

        return $this;
    }

    /** Enable table support and add a table button to the toolbar. */
    public function withTables(bool $condition = true): static
    {
        $this->withTables = $condition;

        if ($condition) {
            $this->toolbarButtons = array_merge($this->toolbarButtons, ['|', 'table']);
        }

        return $this;
    }

    /** Enable text alignment and add alignment buttons to the toolbar. */
    public function withTextAlign(bool $condition = true): static
    {
        $this->withTextAlign = $condition;

        if ($condition) {
            $this->toolbarButtons = array_merge(
                $this->toolbarButtons,
                ['|', 'alignLeft', 'alignCenter', 'alignRight'],
            );
        }

        return $this;
    }

    /** Enable text highlighting and add a highlight button to the toolbar. */
    public function withHighlight(bool $condition = true): static
    {
        $this->withHighlight = $condition;

        if ($condition) {
            $this->toolbarButtons = array_merge($this->toolbarButtons, ['highlight']);
        }

        return $this;
    }

    /** Set the minimum editor height in pixels. */
    public function minHeight(int $pixels): static
    {
        $this->minHeight = $pixels;

        return $this;
    }

    /** Allow at most this many characters. */
    public function maxLength(?int $length): static
    {
        $this->maxLength = $length;

        return $this;
    }

    // ─── Getters ───────────────────────────────────────────────────

    /**
     * @return array<int, string>
     */
    public function getToolbarButtons(): array
    {
        return array_values(
            array_filter(
                $this->toolbarButtons,
                fn (string $btn) => ! in_array($btn, $this->disabledToolbarButtons, true),
            )
        );
    }

    public function getOutputFormat(): string
    {
        return $this->outputFormat;
    }

    public function getMinHeight(): int
    {
        return $this->minHeight;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    public function isWithImages(): bool
    {
        return $this->withImages;
    }

    public function isWithTables(): bool
    {
        return $this->withTables;
    }

    public function isWithTextAlign(): bool
    {
        return $this->withTextAlign;
    }

    public function isWithHighlight(): bool
    {
        return $this->withHighlight;
    }

    /**
     * The canonical {@see HasDefault::default()} value as editor content.
     *
     * The default is pre-formatted markup, not plain text: `->default('<p>Draft
     * <strong>notes</strong></p>')` opens the editor on that document. It is a
     * string because that is what the field stores — HTML for `outputHtml()`, a
     * TipTap JSON document string for `outputJson()`; an HTML default is parsed
     * into a document by the editor either way. Anything non-string (an int, an
     * enum, a closure returning null) is not editor content and yields ''.
     */
    public function getDefaultContent(): string
    {
        $default = $this->getDefault();

        return is_string($default) ? $default : '';
    }

    /**
     * Localised titles for the browser prompts the editor opens (link, image).
     *
     * Resolved here rather than in JS so they follow the app locale like every
     * other string in the field — the bundle ships no vocabulary of its own.
     *
     * @return array<string, string>
     */
    protected function getPromptLabels(): array
    {
        return [
            'linkUrl' => (string) trans('wire-forms::fields.editor.link_url'),
            'imageUrl' => (string) trans('wire-forms::fields.editor.image_url'),
        ];
    }

    /**
     * Whether this editor needs the opt-in extension addon chunk (tables, images,
     * highlight, text-align). When false — the default — the page loads only the
     * core editor entry + shared chunk and never downloads the addon.
     */
    public function needsExtensionAddon(): bool
    {
        return $this->withImages || $this->withTables || $this->withTextAlign || $this->withHighlight;
    }

    /**
     * @return array<string, mixed> Config passed to the Alpine tiptapEditor() component.
     */
    public function getAlpineConfig(): array
    {
        return [
            'wireAttribute' => $this->getWireModelAttribute(),
            'outputFormat' => $this->outputFormat,
            'disabled' => $this->isDisabled(),
            'readOnly' => $this->isReadOnly(),
            'placeholder' => $this->getPlaceholder(),
            'default' => $this->getDefaultContent(),
            'prompts' => $this->getPromptLabels(),
            'maxLength' => $this->maxLength,
            'withImages' => $this->withImages,
            'withTables' => $this->withTables,
            'withTextAlign' => $this->withTextAlign,
            'withHighlight' => $this->withHighlight,
        ];
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.tiptap-editor';
    }
}
