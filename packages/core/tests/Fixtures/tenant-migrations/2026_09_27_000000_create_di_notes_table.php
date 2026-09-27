<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('di_notes', function (Blueprint $table): void {
            $table->id();
            $table->string('body');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('di_notes');
    }
};
