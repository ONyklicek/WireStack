<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use NyonCode\WireModuleTenants\Support\Membership;

/**
 * A company: the tenant of ADR 0040, addressed in URLs by its slug.
 *
 * Deleting is soft — a company holds other people's work, and a mistaken
 * delete should be a restore rather than a restore from backup.
 */
class Tenant extends Model
{
    use SoftDeletes;

    protected $table = 'tenants';

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsToMany<Model, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Membership::userModel(), Membership::table(), 'tenant_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** @return HasMany<TenantInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(TenantInvitation::class, 'tenant_id');
    }
}
