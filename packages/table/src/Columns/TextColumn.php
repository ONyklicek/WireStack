<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Concerns\FormatsState;

class TextColumn extends Column
{
    use FormatsState;

    protected ?string $fontFamily = null;

    public function fontFamily(?string $family): static
    {
        $this->fontFamily = $family;

        return $this;
    }

    public function getFontFamily(): ?string
    {
        return $this->fontFamily;
    }

    public function formatValue(mixed $value, Model $record): string
    {
        if ($value === null || $value === '') {
            return $this->getPlaceholder() ?? '';
        }

        $value = $this->applyNumericAndDateFormatting($value);

        return parent::formatValue($value, $record);
    }
}
