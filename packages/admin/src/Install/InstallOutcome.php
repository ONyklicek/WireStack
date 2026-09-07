<?php

declare(strict_types=1);

namespace NyonCode\WireAdmin\Install;

use NyonCode\WireAdmin\Exceptions\AdminInstallException;

/**
 * What one install step actually did, when it could be done at all.
 *
 * Two cases, both success: something was written, or it was already there and is
 * being left alone. **Failure is not a case here** — a step that cannot be taken
 * throws {@see AdminInstallException}, so an
 * installer can never print a tick beside something that did not happen, and a
 * caller cannot forget to check.
 */
enum InstallOutcome: string
{
    /** The step wrote something that was not there. */
    case Created = 'created';

    /** It was already done, and nothing was touched. */
    case AlreadyPresent = 'already-present';
}
