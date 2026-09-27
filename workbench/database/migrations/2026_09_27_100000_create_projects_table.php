<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The projects each company owns — the fixture verify-tenants drives (ADR 0040).
 * The companies and who belongs to which are wire-module-tenants' tables, which
 * the workbench publishes like every other package migration.
 */
return new class extends Migration
{
    public function up(): void
    {
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
    }
};
