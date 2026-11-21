<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assisted_sales', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('source', 20);
            $table->string('customer_label')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 25)->nullable();
            $table->text('internal_note')->nullable();
            $table->json('items');
            $table->json('snapshot');
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('order_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('assisted_sale_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assisted_sale_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->text('token');
            $table->json('items');
            $table->json('snapshot');
            $table->unsignedBigInteger('subtotal_minor');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assisted_sale_links');
        Schema::dropIfExists('assisted_sales');
    }
};
