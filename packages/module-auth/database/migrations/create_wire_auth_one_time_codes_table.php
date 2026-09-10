<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create((string) config('wire-module-auth.codes.table', 'wire_auth_one_time_codes'), function (Blueprint $table): void {
            $table->id();

            // The pair is the address of a code, not two columns that happen to
            // be looked up together: a code mailed to confirm an address must
            // not open the sign-in challenge (ADR 0037 §1). Unique, because
            // issuing replaces — a person who asks twice has one code, and it is
            // the one in the newest mail.
            $table->string('purpose', 32);
            $table->string('identifier');
            $table->unique(['purpose', 'identifier']);

            // Hashed the way Laravel hashes a reset token. A database copy is
            // then a list of dead hashes rather than a set of live credentials.
            $table->string('code');

            // What the flow has to carry from the mail to the form. Today that
            // is the reset broker's token, which is why this column exists at
            // all: the code stands in for the token and the token still does the
            // resetting.
            $table->text('payload')->nullable();

            // Wrong guesses, counted on the code itself. The route throttle
            // counts an address and an IP, and neither is the thing being
            // guessed.
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
