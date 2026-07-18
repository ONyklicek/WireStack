<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\InteractsWithStateColor;
use NyonCode\WireCore\Foundation\Concerns\InteractsWithStateIcon;
use NyonCode\WireCore\Foundation\Icons\IconManager;

class BadgeColumn extends Column
{
    // colors()/colorUsing()/getColorForState() come from InteractsWithStateColor;
    // icons()/iconUsing()/getIconForState() from InteractsWithStateIcon.
    use InteractsWithStateColor;
    use InteractsWithStateIcon;

    // size()/getSize() come from Foundation\Concerns\HasSize (via Column).

    public function renderCell(Model $record): string
    {
        if (! $this->canView() || ! $this->isVisibleForRecord($record)) {
            return '';
        }

        $state = $this->getState($record);

        if ($state === null || $state === '') {
            return $this->getEmptyCellText();
        }

        $color = $this->getColorForState($state);
        $icon = $this->getIconForState($state);

        // §7: the badge markup is a function of its (low-cardinality) state, so the
        // view render is memoised by its data — rows sharing a status reuse one render.
        return $this->renderViewCached('tables.columns.badge', [
            'sizeClasses' => $this->getSizeClasses(),
            'colorClasses' => $this->getColorClasses($color),
            'iconHtml' => $icon ? app(IconManager::class)->render($icon, 'w-3.5 h-3.5 mr-1') : '',
            // formatValue() returns raw text — the view escapes it unless the column
            // opted into ->html(), mirroring the base text cell. Without this a
            // record value like `<img onerror=…>` was rendered raw = stored XSS.
            'displayValue' => $this->formatValue($state, $record),
            'isHtml' => $this->html,
        ]);
    }

    public function getColorClasses(?string $color): string
    {
        return self::getBadgeColorClasses($color ?? Color::Gray->value);
    }

    public function getSizeClasses(): string
    {
        return self::getBadgeSizeClasses($this->getSize());
    }
}
