<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'product_variants'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->unsignedBigInteger('usd_price_minor')->nullable();
                $table->unsignedBigInteger('pkr_price_minor')->nullable();
            });
        }
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->unsignedInteger('on_hand')->nullable();
            $table->unsignedInteger('reserved')->default(0);
        });
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->string('guest_hash', 64)->index();
            $table->string('access_token', 64)->unique();
            $table->string('status')->default('pending')->index();
            $table->string('payment_method');
            $table->string('payment_status')->default('unpaid')->index();
            $table->char('currency', 3);
            $table->json('customer');
            $table->text('payment_instructions')->nullable();
            foreach (['subtotal', 'discount', 'tax', 'shipping', 'total'] as $amount) {
                $table->unsignedBigInteger($amount.'_minor');
            }
            $table->unsignedInteger('tax_basis_points');
            $table->string('shipping_name');
            $table->timestamp('expires_at')->nullable()->index();
            $table->string('proof_path')->nullable();
            $table->timestamp('notification_logged_at')->nullable();
            $table->timestamps();
        });
        Schema::create('order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->string('color');
            $table->string('size');
            $table->string('sku');
            $table->unsignedInteger('quantity');
            $table->char('currency', 3);
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('total_minor');
            $table->timestamps();
        });
        Schema::create('checkout_attempts', function (Blueprint $table): void {
            $table->uuid('key')->primary();
            $table->string('guest_hash', 64);
            $table->string('payload_hash', 64);
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('on_hand_delta');
            $table->integer('reserved_delta');
            $table->unsignedInteger('on_hand_after');
            $table->unsignedInteger('reserved_after');
            $table->string('reason', 500);
            $table->timestamps();
        });
        Schema::create('order_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->string('payment_status');
            $table->string('note', 500);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['order_histories', 'stock_movements', 'checkout_attempts', 'order_lines', 'orders'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('product_variants', fn (Blueprint $t) => $t->dropColumn(['on_hand', 'reserved']));
        foreach (['products', 'product_variants'] as $name) {
            Schema::table($name, fn (Blueprint $t) => $t->dropColumn(['usd_price_minor', 'pkr_price_minor']));
        }
    }
};
