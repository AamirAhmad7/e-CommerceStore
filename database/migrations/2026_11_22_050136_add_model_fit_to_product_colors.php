<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_colors', function (Blueprint $table): void {
            $table->unsignedSmallInteger('model_height_cm')->nullable();
            $table->string('model_size_worn', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_colors', fn (Blueprint $table) => $table->dropColumn(['model_height_cm', 'model_size_worn']));
    }
};
