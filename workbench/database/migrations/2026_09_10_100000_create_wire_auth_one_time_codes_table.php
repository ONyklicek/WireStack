<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The codes table, as an application that published it would have it.
     *
     * `wire-module-auth` publishes this migration rather than running it from the
     * package — the table belongs to the application's schema — so the workbench
     * stands in for the application that ran `vendor:publish` and `migrate`. It
     * is the same shape as `packages/module-auth/database/migrations/`; a
     * `require` of that file would work and would hide which schema the preview
     * server is actually running.
     */
    public function up(): void
    {
        $table = (string) config('wire-module-auth.codes.table', 'wire_auth_one_time_codes');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $table): void {
            $table->id();
            $table->string('purpose', 32);
            $table->string('identifier');
            $table->unique(['purpose', 'identifier']);
            $table->string('code');
            $table->text('payload')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('wire-module-auth.codes.table', 'wire_auth_one_time_codes'));
    }
};
