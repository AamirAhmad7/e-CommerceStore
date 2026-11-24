<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->unsignedBigInteger('purchase_cost_minor')->nullable());
        Schema::table('order_lines', fn (Blueprint $table) => $table->unsignedBigInteger('unit_cost_minor')->nullable());
    }

    public function down(): void
    {
        Schema::table('order_lines', fn (Blueprint $table) => $table->dropColumn('unit_cost_minor'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('purchase_cost_minor'));
    }
};
