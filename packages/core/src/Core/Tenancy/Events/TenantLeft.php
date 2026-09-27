<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Tenancy\Events;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Tenancy\CurrentTenant;

/** A tenant has just stopped being the one being worked in ({@see CurrentTenant::leave()}). */
final readonly class TenantLeft
{
    public function __construct(public Model $tenant) {}
}
