<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reorderable_column_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('model_type');
            $table->json('column_order');
            $table->timestamps();

            $table->unique(['user_id', 'model_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reorderable_column_orders');
    }
};
