<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use NyonCode\PermissionExtended\Traits\HasRoles;
use NyonCode\WireCore\Foundation\Contracts\HasAvatar;
use NyonCode\WireModuleUsers\Concerns\InteractsWithAvatar;
use Workbench\Database\Factories\UserFactory;

#[Fillable(['name', 'email', 'password', 'role', 'bio', 'is_active', 'avatar_path'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements HasAvatar
{
    /** @use HasFactory<UserFactory> */
    // `HasRoles` is this stack's, from nyoncode/laravel-permission-extended, and
    // not the Spatie trait it extends: the module's role screens look for exactly
    // this one, so a model on bare Spatie is deliberately not detected.
    use HasFactory, HasRoles, InteractsWithAvatar, Notifiable, TwoFactorAuthenticatable;

    /**
     * The relation `wire-module-users.teams.relation` names.
     *
     * The switcher in the top bar lists what this returns, and
     * `Teams::switchTo()` checks membership against it again before storing
     * anything — a select is markup, and markup is whatever reached the browser.
     *
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
