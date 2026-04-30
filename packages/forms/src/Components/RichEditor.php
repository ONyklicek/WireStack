<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

/**
 * Rich text editor field with configurable toolbar buttons.
 */
class RichEditor extends Field
{
    protected ?array $toolbarButtons = null;

    protected array $disabledToolbarButtons = [];

    protected ?int $maxLength = null;

    protected ?string $fileAttachmentsDirectory = null;

    public function toolbarButtons(array $buttons): static
    {
        $this->toolbarButtons = $buttons;

        return $this;
    }

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

    public function getToolbarButtons(): array
    {
        $buttons = $this->toolbarButtons ?? config('wire-forms.rich_editor.toolbar', [
            'bold', 'italic', 'underline', 'strike',
            'h2', 'h3',
            'bulletList', 'orderedList',
            'link', 'blockquote', 'codeBlock',
            'undo', 'redo',
        ]);

        return array_diff($buttons, $this->disabledToolbarButtons);
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    public function getFileAttachmentsDirectory(): ?string
    {
        return $this->fileAttachmentsDirectory;
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.rich-editor';
    }
}
