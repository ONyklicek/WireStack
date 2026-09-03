<?php

declare(strict_types=1);

namespace Workbench\App\Enums;

use NyonCode\WireCore\Foundation\Contracts\Enum\HasColor;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasIcon;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;

/**
 * The states a workbench invoice moves through.
 *
 * Implements all three canonical enum contracts, which is what lets the badge in
 * the table and the transition buttons agree about what a state looks like
 * without either of them holding a colour map — the reason `WorkflowState` holds
 * no colour, label or icon of its own.
 *
 * The values match what the seeder writes, so this is the workbench's real data
 * rather than a fixture beside it.
 */
enum InvoiceStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Paid = 'paid';
    case Overdue = 'overdue';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Pending => 'warning',
            self::Paid => 'success',
            self::Overdue => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Draft => 'outline:pencil',
            self::Pending => 'outline:clock',
            self::Paid => 'outline:check-circle',
            self::Overdue => 'outline:exclamation-triangle',
        };
    }
}
