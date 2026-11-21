<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('code', 40)->nullable()->unique();
            $t->boolean('automatic')->default(false);
            $t->string('type', 20);
            $t->unsignedInteger('percent_basis_points')->nullable();
            $t->unsignedBigInteger('amount_minor')->nullable();
            $t->unsignedBigInteger('cap_minor')->nullable();
            $t->string('scope', 20)->default('storewide');
            $t->json('target_ids')->nullable();
            $t->json('example_items')->nullable();
            $t->unsignedBigInteger('minimum_minor')->nullable();
            $t->unsignedInteger('minimum_units')->nullable();
            $t->string('status', 20)->default('draft');
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->unsignedInteger('use_limit')->nullable();
            $t->unsignedInteger('uses')->default(0);
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('landing_collection_id')->nullable()->constrained('collections')->nullOnDelete();
            $t->timestamps();
            $t->index(['status', 'automatic', 'starts_at']);
        });
        Schema::table('orders', function (Blueprint $t): void {
            $t->foreignId('promotion_id')->nullable()->constrained()->restrictOnDelete();
            $t->json('promotion_snapshot')->nullable();
        });
        Schema::table('order_lines', function (Blueprint $t): void {
            $t->unsignedBigInteger('promotion_discount_minor')->default(0);
            $t->json('unit_paid_minor')->nullable();
            $t->boolean('promotion_eligible')->default(false);
        });
        Schema::create('promotion_redemptions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('promotion_id')->constrained()->restrictOnDelete();
            $t->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $t->unsignedInteger('eligible_units');
            $t->unsignedBigInteger('eligible_minor');
            $t->unsignedBigInteger('merchandise_discount_minor');
            $t->unsignedBigInteger('shipping_discount_minor');
            $t->timestamps();
            $t->index(['promotion_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_redemptions');
        Schema::table('order_lines', fn (Blueprint $t) => $t->dropColumn(['promotion_discount_minor', 'unit_paid_minor', 'promotion_eligible']));
        Schema::table('orders', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('promotion_id');
            $t->dropColumn('promotion_snapshot');
        });
        Schema::dropIfExists('promotions');
    }
};
