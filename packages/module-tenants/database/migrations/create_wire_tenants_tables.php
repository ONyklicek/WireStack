<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireModuleTenants\Support\Membership;

/**
 * Companies, who belongs to which and in what role, and invitations to join.
 *
 * The pivot is `wire-core.tenancy.members_table` (`tenant_user`), the one
 * `InteractsWithTenants` reads membership from, with the role beside it. Its two
 * columns follow Laravel's convention for the configured models — `tenant_id`
 * and `user_id` for the defaults — which is the convention that trait reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        $members = Membership::table();

        if (! Schema::hasTable($members)) {
            Schema::create($members, function (Blueprint $table): void {
                $table->foreignId(Membership::tenantKey());
                $table->foreignId(Membership::userKey());
                $table->string('role')->default('member');
                $table->timestamps();
                $table->primary([Membership::tenantKey(), Membership::userKey()]);
            });
        }

        if (! Schema::hasTable('tenant_invitations')) {
            Schema::create('tenant_invitations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->index();
                $table->string('email');
                $table->string('role')->default('member');
                $table->foreignId('invited_by')->nullable();
                $table->dateTime('expires_at');
                $table->timestamp('accepted_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_invitations');
        Schema::dropIfExists(Membership::table());
        Schema::dropIfExists('tenants');
    }
};
