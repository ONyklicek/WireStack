<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use NyonCode\WireModuleTenants\Enums\MemberRole;

/**
 * An invitation to join a company, sent to an e-mail address.
 *
 * Accepted once, by a signed-in person with that address, before it expires.
 * The link is a signed URL over this row, so there is no token to store.
 *
 * @property string $email
 * @property MemberRole $role
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 */
class TenantInvitation extends Model
{
    protected $table = 'tenant_invitations';

    protected $guarded = [];

    protected $casts = [
        'role' => MemberRole::class,
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    /** @return BelongsTo<Model, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo((string) config('wire-module-tenants.model', Tenant::class), 'tenant_id');
    }

    public function isOpen(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }
}
