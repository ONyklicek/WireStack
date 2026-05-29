<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Audit\Actions;

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Core\Support\Trans;

/**
 * Row action that opens a slide-over panel with the audit trail for a record.
 *
 * Usage:
 *   Table::make()
 *       ->actions([
 *           AuditTrailAction::make(),
 *       ])
 *
 * @phpstan-consistent-constructor
 */
class AuditTrailAction extends Action
{
    public function __construct(string $name = 'auditTrail')
    {
        parent::__construct($name);

        $this->label(Trans::get('wire-core::audit.trail_label'))
            ->icon('clock')
            ->color('gray')
            ->slideOver()
            ->modalHeading(Trans::get('wire-core::audit.trail_heading'))
            ->modalWidth('lg')
            ->stickyHeader();
    }

    public static function make(string $name = 'auditTrail'): static
    {
        return new static($name);
    }
}
