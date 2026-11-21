<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t): void {
            $t->timestamp('delivered_at')->nullable();
            $t->unsignedBigInteger('collected_minor')->default(0);
        });
        DB::table('orders')->where('payment_status', 'paid')->update(['collected_minor' => DB::raw('total_minor')]);
        DB::table('orders')->where('status', 'delivered')->orderBy('id')->each(function ($order): void {
            DB::table('orders')->where('id', $order->id)->update(['delivered_at' => DB::table('order_histories')->where('order_id', $order->id)->where('status', 'delivered')->min('created_at')]);
        });
        Schema::create('order_requests', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('order_id')->constrained()->restrictOnDelete();
            $t->uuid('submission_key');
            $t->string('type', 20);
            $t->string('status', 20)->default('pending');
            $t->text('reason');
            $t->text('explanation')->nullable();
            $t->text('internal_note')->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->timestamp('received_at')->nullable();
            $t->timestamps();
            $t->unique(['order_id', 'submission_key']);
            $t->index(['type', 'status']);
        });
        Schema::create('order_request_lines', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('order_request_id')->constrained()->restrictOnDelete();
            $t->foreignId('order_line_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('quantity');
            $t->unique(['order_request_id', 'order_line_id']);
        });
        Schema::create('return_receipts', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('order_request_line_id')->unique()->constrained()->restrictOnDelete();
            $t->unsignedInteger('resellable');
            $t->unsignedInteger('damaged');
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->text('note')->nullable();
            $t->timestamp('created_at');
        });
        Schema::create('order_refunds', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('order_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('amount_minor');
            $t->string('currency', 3);
            $t->string('reference', 150);
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->timestamp('created_at');
            $t->unique(['order_id', 'reference']);
        });
        Schema::create('order_events', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('order_id')->constrained()->restrictOnDelete();
            $t->string('event_key', 150);
            $t->string('subject');
            $t->text('message');
            $t->boolean('customer_visible')->default(true);
            $t->string('recipient');
            $t->longText('body');
            $t->timestamp('sent_at')->nullable();
            $t->text('last_error')->nullable();
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamps();
            $t->unique(['order_id', 'event_key']);
        });
    }

    public function down(): void
    {
        foreach (['order_events', 'order_refunds', 'return_receipts', 'order_request_lines', 'order_requests'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['delivered_at', 'collected_minor']));
    }
};
