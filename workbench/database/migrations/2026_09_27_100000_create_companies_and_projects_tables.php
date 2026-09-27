<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Companies (the tenants), who belongs to which, and projects each company
 * owns — the fixture verify-tenants drives (ADR 0040).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
        });

        Schema::create('company_user', function (Blueprint $table): void {
            $table->foreignId('company_id');
            $table->foreignId('user_id');
            $table->primary(['company_id', 'user_id']);
        });

        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('status')->default('active');
            $table->foreignId('tenant_id')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
        Schema::dropIfExists('company_user');
        Schema::dropIfExists('companies');
    }
};
