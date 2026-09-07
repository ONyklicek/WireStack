<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAudit\Pages;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireModuleAudit\Resources\AuditResource;
use NyonCode\WireModuleAudit\Support\AuditedRecords;
use NyonCode\WireModuleAudit\Support\AuditEvents;
use NyonCode\WirePanels\Resources\Pages\ViewPage;

/**
 * One entry: what moved, who moved it, and where the request came from.
 *
 * Titled by what it is about rather than by the resource — "Invoice #7 ·
 * Updated" instead of "Audit entry", which is what the row that was clicked
 * said and what the breadcrumb above it has to say to be worth drawing.
 *
 * The record is memoised for the length of the request because the title is
 * asked for twice — once as the heading, once as the last crumb — and
 * `resolveRecord()` is a query each time.
 */
class ViewAuditEntry extends ViewPage
{
    protected static ?string $resource = AuditResource::class;

    /** Not public, so it stays out of the snapshot Livewire carries. */
    protected ?Model $entry = null;

    public function getTitle(): ?string
    {
        // An application that titled the page itself keeps its title; this is a
        // better default, not an override of a decision somebody made.
        if ($this->title !== null) {
            return parent::getTitle();
        }

        $entry = $this->entry();

        if ($entry === null) {
            return parent::getTitle();
        }

        $label = AuditedRecords::label($entry->auditable_type, $entry->auditable_id);
        $event = AuditEvents::label((string) $entry->event);

        return $label === '' ? $event : $label.' · '.$event;
    }

    /** This page's record, resolved once. */
    protected function entry(): ?Model
    {
        if ($this->entry instanceof Model) {
            return $this->entry;
        }

        $record = $this->resolveRecord();

        return $this->entry = $record instanceof Model ? $record : null;
    }
}
