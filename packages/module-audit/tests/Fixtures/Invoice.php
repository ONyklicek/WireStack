<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAudit\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** Something an application audits, so the log has a record to point back at. */
class Invoice extends Model
{
    protected $table = 'invoices';

    protected $guarded = [];

    public $timestamps = false;
}
