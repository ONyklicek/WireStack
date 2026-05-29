<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

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

    protected ?string $fileAttachmentsDirectory = null;

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
     * @param  array<int, string>  $buttons  Button keys + '|' separators.
     */
    public function toolbarButtons(array $buttons): static
    {
        $this->toolbarButtons = $buttons;

        return $this;
    }

    /**
     * @param  array<int, string>  $buttons  Keys to remove from the toolbar.
     */
    public function disableToolbarButtons(array $buttons): static
    {
        $this->disabledToolbarButtons = $buttons;

        return $this;
    }

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

    public function withImages(bool $condition = true): static
    {
        $this->withImages = $condition;

        if ($condition) {
            $this->toolbarButtons = array_merge($this->toolbarButtons, ['|', 'image']);
        }

        return $this;
    }

    public function withTables(bool $condition = true): static
    {
        $this->withTables = $condition;

        if ($condition) {
            $this->toolbarButtons = array_merge($this->toolbarButtons, ['|', 'table']);
        }

        return $this;
    }

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

    public function withHighlight(bool $condition = true): static
    {
        $this->withHighlight = $condition;

        if ($condition) {
            $this->toolbarButtons = array_merge($this->toolbarButtons, ['highlight']);
        }

        return $this;
    }

    public function minHeight(int $pixels): static
    {
        $this->minHeight = $pixels;

        return $this;
    }

    public function maxLength(?int $length): static
    {
        $this->maxLength = $length;

        return $this;
    }

    public function fileAttachmentsDirectory(?string $directory): static
    {
        $this->fileAttachmentsDirectory = $directory;

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

    public function getFileAttachmentsDirectory(): ?string
    {
        return $this->fileAttachmentsDirectory;
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
