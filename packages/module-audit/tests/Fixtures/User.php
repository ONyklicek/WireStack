<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAudit\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** The application's user model, as far as these tests are concerned. */
class User extends Model
{
    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}
