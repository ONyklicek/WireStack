<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasColor;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasIcon;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Infolists\Components\IconEntry;
use NyonCode\WireCore\Infolists\Components\TextEntry;

enum EceBacked: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

enum EceRich: string implements HasColor, HasIcon, HasLabel
{
    case Open = 'open';
    case Closed = 'closed';

    public function getLabel(): ?string
    {
        return $this === self::Open ? 'Otevřeno' : 'Zavřeno';
    }

    public function getColor(): string|Color|null
    {
        return $this === self::Open ? Color::Success : Color::Danger;
    }

    public function getIcon(): string|Icon|null
    {
        return $this === self::Open ? Icon::check : Icon::xMark;
    }
}

it('formats a backed enum entry as its scalar value', function () {
    $entry = TextEntry::make('status')->record(['status' => EceBacked::Active]);

    expect($entry->getFormattedState())->toBe('active');
});

it('formats a HasLabel enum entry as its label', function () {
    $entry = TextEntry::make('phase')->record(['phase' => EceRich::Open]);

    expect($entry->getFormattedState())->toBe('Otevřeno');
});

it('auto-resolves icon entry glyph and color from enum contracts', function () {
    $entry = IconEntry::make('phase')->record(['phase' => EceRich::Closed]);

    expect($entry->getResolvedIcon())->toBe('x-mark')
        ->and($entry->getResolvedColor())->toBe('danger');
});
