<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Actions;

/**
 * Click resolver for actions rendered inside the table's empty state.
 *
 * The empty state is a record-less surface — there are no rows — so its actions
 * run through exactly the same host methods as the toolbar's header actions,
 * rather than the row pipeline, which would carry an empty record key. That
 * mapping has one owner, {@see HeaderActionClickResolver}; this name stays
 * because the empty state is where a caller looks for it.
 */
final class EmptyStateActionClickResolver extends HeaderActionClickResolver {}
