<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The credential rows `laravel/passkeys` publishes.
     *
     * Same shape as its own migration, written out here rather than required for
     * the reason the codes table is: this workbench stands in for the application
     * that ran `vendor:publish --tag=passkeys-migrations`, and a `require` of a
     * vendor file would hide which schema the preview server is running.
     */
    public function up(): void
    {
        if (Schema::hasTable('passkeys')) {
            return;
        }

        Schema::create('passkeys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('credential_id')->unique();
            $table->json('credential');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passkeys');
    }
};
