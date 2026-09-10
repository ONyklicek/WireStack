<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Tests\Fixtures;

use NyonCode\WireModuleAuth\Contracts\ReceivesLoginCodes;

/**
 * A user model that answers the second-factor question for itself, and says no.
 *
 * The half of ADR 0037 §4 a config switch cannot express: the flow is on for the
 * installation, and this account still signs in with a password alone.
 */
class OptOutUser extends CodeUser implements ReceivesLoginCodes
{
    public function wantsLoginCode(): bool
    {
        return false;
    }
}
