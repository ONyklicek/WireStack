<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NyonCode\WirePanels\Concerns\InteractsWithTenants;
use NyonCode\WirePanels\Contracts\HasTenants;

class User extends Authenticatable implements HasTenants
{
    use InteractsWithTenants;
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];
}
