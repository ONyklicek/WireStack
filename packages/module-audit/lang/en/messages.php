<?php

declare(strict_types=1);

/*
 * What this module says, and only that.
 *
 * The words shared with core's trail slide-over — "System", "Unknown user",
 * the field/old/new headings and "(empty)" — are deliberately **not** here:
 * they live in `wire-core::audit`, and one screen contradicting the other about
 * who "System" is would be the kind of small lie an audit log cannot afford.
 *
 * The event names are here, though, because core's are sentence fragments for a
 * timeline ("updated this record") — the right shape there, the wrong one inside
 * a badge or a filter.
 */

return [
    'system' => 'System',
    'entry' => 'Audit entry',
    'entries' => 'Audit log',

    'event' => 'Event',
    'record' => 'Record',
    'actor' => 'Actor',
    'when' => 'When',
    'changed' => 'Changed',

    'event_created' => 'Created',
    'event_updated' => 'Updated',
    'event_deleted' => 'Deleted',
    'event_bulk_action' => 'Bulk action',
    'event_cell_updated' => 'Cell edited',

    'what_happened' => 'What happened',
    'changes' => 'Changes',
    'no_changes' => 'No field changed.',

    'context' => 'Request',
    'context_key' => 'Detail',
    'context_value' => 'Value',
    'no_context' => 'Recorded outside a request.',

    'open_record' => 'Open record',

    'empty_heading' => 'Nothing recorded yet',
    'empty_description' => 'Entries appear here as audited models are created, changed and deleted.',
];
