<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Contracts;

/**
 * A resource whose soft-deleted records are managed in the panel, not hidden by it.
 *
 * A marker, because what it switches on is all derived from the model the
 * resource already names — which must use `SoftDeletes`:
 *
 * - the list gains a *Trashed* filter, *Restore* and *Force delete* on a trashed
 *   row, and both as bulk actions over a selection;
 * - a record page opens a trashed record rather than answering 404, and offers
 *   `restoreHeaderAction()` / `forceDeleteHeaderAction()` to a page that asks.
 *
 * Every one of those actions is asked of the model's policy (`restore`,
 * `forceDelete`) when it has one, and of the record's edit page otherwise —
 * the rule *Delete* already follows.
 */
interface ManagesTrashedRecords {}
