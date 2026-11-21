<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $t): void {
            $t->id();
            $t->string('name')->index();
            $t->string('contact_person')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('whatsapp', 20)->nullable();
            $t->string('city')->nullable();
            $t->string('email')->nullable();
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('supplier_sources', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $t->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $t->unique(['supplier_id', 'product_variant_id']);
            $t->string('supplier_sku')->nullable()->index();
            $t->boolean('is_preferred')->default(false);
            $t->unsignedInteger('pack_size')->default(1);
            $t->unsignedInteger('minimum_quantity')->default(1);
            $t->unsignedInteger('lead_days')->nullable();
            $t->unsignedBigInteger('quoted_cost_minor')->nullable();
            $t->timestamp('quoted_at')->nullable();
            $t->timestamps();
        });
        Schema::table('product_variants', function (Blueprint $t): void {
            $t->unsignedInteger('reorder_point')->nullable();
            $t->unsignedInteger('reorder_target')->nullable();
        });
        Schema::create('purchase_orders', function (Blueprint $t): void {
            $t->id();
            $t->string('reference')->unique();
            $t->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $t->string('supplier_name');
            $t->string('supplier_reference')->nullable();
            $t->date('expected_at')->nullable()->index();
            $t->text('notes')->nullable();
            $t->unsignedBigInteger('freight_minor')->nullable();
            $t->string('status', 30)->default('draft')->index();
            $t->unsignedInteger('version')->default(1);
            $t->timestamp('issued_at')->nullable();
            $t->string('closure_reason')->nullable();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('purchase_order_lines', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $t->unique(['purchase_order_id', 'product_variant_id'], 'po_variant_unique');
            $t->string('product_name');
            $t->string('color');
            $t->string('size');
            $t->string('sku')->index();
            $t->string('supplier_sku')->nullable();
            $t->unsignedInteger('quantity');
            $t->unsignedBigInteger('unit_cost_minor')->nullable();
            foreach (['accepted', 'damaged_replace', 'damaged_short', 'short_closed'] as $field) {
                $t->unsignedInteger($field)->default(0);
            } $t->timestamps();
        });
        Schema::create('purchase_receipts', function (Blueprint $t): void {
            $t->id();
            $t->string('reference')->unique();
            $t->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $t->uuid('submission_key')->unique();
            $t->string('payload_hash', 64);
            $t->string('kind', 20);
            $t->foreignId('correction_of_id')->nullable()->constrained('purchase_receipts')->restrictOnDelete();
            $t->string('delivery_reference')->nullable();
            $t->text('reason')->nullable();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('purchase_receipt_lines', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('purchase_receipt_id')->constrained()->restrictOnDelete();
            $t->foreignId('purchase_order_line_id')->constrained()->restrictOnDelete();
            $t->unique(['purchase_receipt_id', 'purchase_order_line_id'], 'receipt_po_line_unique');
            foreach (['accepted', 'damaged_replace', 'damaged_short', 'short_closed'] as $field) {
                $t->integer($field)->default(0);
            } $t->unsignedBigInteger('unit_cost_minor')->nullable();
            $t->timestamps();
        });
        Schema::table('stock_movements', function (Blueprint $t): void {
            $t->foreignId('purchase_receipt_line_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::create('purchase_draft_batches', function (Blueprint $t): void {
            $t->uuid('key')->primary();
            $t->string('payload_hash', 64);
            $t->json('order_ids')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_draft_batches');
        Schema::table('stock_movements', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('purchase_receipt_line_id');
        });
        foreach (['purchase_receipt_lines', 'purchase_receipts', 'purchase_order_lines', 'purchase_orders', 'supplier_sources', 'suppliers'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('product_variants', function (Blueprint $t): void {
            $t->dropColumn(['reorder_point', 'reorder_target']);
        });
    }
};
