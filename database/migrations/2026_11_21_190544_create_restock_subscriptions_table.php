<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restock_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('email_hash', 64);
            $table->text('email');
            $table->text('token');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('confirmation_expires_at');
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['product_variant_id', 'email_hash']);
            $table->index(['product_variant_id', 'completed_at', 'cancelled_at', 'confirmed_at'], 'restock_waiting_variant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restock_subscriptions');
    }
};
