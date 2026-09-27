<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Companies, who belongs to which and in what role, and invitations to join.
 *
 * `tenant_user` is the pivot `InteractsWithTenants` reads membership from
 * (`wire-core.tenancy.members_table`), with the role beside it.
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

        if (! Schema::hasTable('tenant_user')) {
            Schema::create('tenant_user', function (Blueprint $table): void {
                $table->foreignId('tenant_id');
                $table->foreignId('user_id');
                $table->string('role')->default('member');
                $table->timestamps();
                $table->primary(['tenant_id', 'user_id']);
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
        Schema::dropIfExists('tenant_user');
        Schema::dropIfExists('tenants');
    }
};
