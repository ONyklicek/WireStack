<?php

declare(strict_types=1);

namespace Workbench\App\Enums;

use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;

enum PreviewStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }
}
