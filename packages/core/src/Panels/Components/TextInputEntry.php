<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Panels\Components;

/**
 * Text input entry — an inline text field that writes directly to the record,
 * saving on blur / Enter with escape-to-revert (the shared `wireEditableCell`
 * text mode). Supports the common HTML input types via {@see type()}.
 */
class TextInputEntry extends EditableEntry
{
    protected string $editableType = 'text';

    protected string $inputType = 'text';

    /**
     * HTML input type, e.g. 'text', 'number', 'email', 'url'.
     */
    public function type(string $type): static
    {
        $this->inputType = $type;

        return $this;
    }

    public function getInputType(): string
    {
        return $this->inputType;
    }

    protected function viewName(): string
    {
        return 'wire-core::panels.entries.text-input';
    }
}
