<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Contracts;

use NyonCode\WirePanels\Routing\ZoneDirectory;

/**
 * A user who has a zone of their own to land in after signing in.
 *
 * Implemented on the application's user model. The answer is a zone key —
 * `obchod`, `admin` — or null for "no preference", and it is a wish rather than
 * a key to the door: a zone the person may not enter, or one that is not routed
 * at all, is passed over and the configured primary zone decides instead
 * ({@see ZoneDirectory::landingFor()}).
 */
interface HasPreferredZone
{
    public function preferredZone(): ?string;
}
