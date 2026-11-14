<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Preferences\Contracts;

/**
 * A {@see PreferenceDriver} whose user-less views are seen by everyone.
 *
 * The database driver keys a row on (surface, user, view), so a row with no
 * user is one every user can read — a shared view. The session driver has no
 * "everyone": its null user is the current session, and a view "shared" there
 * would be seen by nobody else. A surface offers sharing only through a driver
 * that says it can.
 */
interface SharesViews {}
