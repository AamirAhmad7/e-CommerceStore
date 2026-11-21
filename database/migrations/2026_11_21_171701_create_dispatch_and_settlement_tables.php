<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('couriers')) {
            Schema::create('couriers', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->string('service_name')->nullable();
                $table->string('tracking_template', 1000)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('dispatch_manifests')) {
            Schema::create('dispatch_manifests', function (Blueprint $table): void {
                $table->id();
                $table->uuid('submission_key')->unique();
                $table->string('payload_hash', 64);
                $table->string('reference')->unique();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('shipments')) {
            Schema::create('shipments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
                $table->foreignId('courier_id')->nullable()->constrained()->restrictOnDelete();
                $table->foreignId('dispatch_manifest_id')->nullable()->constrained()->restrictOnDelete();
                $table->string('courier_name')->nullable();
                $table->string('service_name')->nullable();
                $table->string('tracking_template', 1000)->nullable();
                $table->string('tracking_number', 150)->nullable();
                $table->unique(['courier_id', 'tracking_number']);
                $table->string('stage', 30)->index();
                $table->unsignedInteger('version')->default(1);
                $table->string('pack_key', 64)->nullable();
                $table->unsignedBigInteger('expected_cod_minor')->default(0);
                $table->unsignedBigInteger('collected_minor')->default(0);
                $table->string('collection_reference', 400)->nullable();
                $table->timestamp('collection_confirmed_at')->nullable();
                $table->timestamp('packed_at')->nullable();
                $table->timestamp('dispatched_at')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('courier_return_receipts')) {
            Schema::create('courier_return_receipts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shipment_id')->constrained()->restrictOnDelete();
                $table->foreignId('order_line_id')->unique()->constrained()->restrictOnDelete();
                $table->unsignedInteger('resellable');
                $table->unsignedInteger('damaged');
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('note')->nullable();
                $table->timestamp('created_at');
            });
        }
        if (! Schema::hasTable('courier_settlements')) {
            Schema::create('courier_settlements', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('courier_id')->constrained()->restrictOnDelete();
                $table->string('external_reference', 150);
                $table->unique(['courier_id', 'external_reference']);
                $table->string('payload_hash', 64);
                $table->date('payout_date');
                $table->string('bank_reference', 400);
                $table->text('review_note')->nullable();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('courier_settlement_lines')) {
            Schema::create('courier_settlement_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('courier_settlement_id')->constrained()->restrictOnDelete();
                $table->foreignId('shipment_id')->unique()->constrained()->restrictOnDelete();
                $table->string('tracking_number', 150);
                $table->unsignedBigInteger('collection_minor');
                $table->unsignedBigInteger('gross_minor');
                $table->unsignedBigInteger('fee_minor');
                $table->unsignedBigInteger('net_minor');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('courier_settlement_adjustments')) {
            Schema::create('courier_settlement_adjustments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('courier_settlement_line_id');
                $table->string('reference', 150);
                $table->unique(['courier_settlement_line_id', 'reference'], 'settlement_adjustment_reference_unique');
                $table->bigInteger('gross_delta');
                $table->bigInteger('fee_delta');
                $table->bigInteger('net_delta');
                $table->text('reason');
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamp('created_at');
            });
        }
        $foreignKeys = array_column(Schema::getForeignKeys('courier_settlement_adjustments'), 'name');
        if (! in_array('settlement_adjustment_line_fk', $foreignKeys, true)) {
            Schema::table('courier_settlement_adjustments', function (Blueprint $table): void {
                $table->foreign('courier_settlement_line_id', 'settlement_adjustment_line_fk')->references('id')->on('courier_settlement_lines')->restrictOnDelete();
            });
        }
        if (! in_array('settlement_adjustment_actor_fk', $foreignKeys, true)) {
            Schema::table('courier_settlement_adjustments', function (Blueprint $table): void {
                $table->foreign('actor_id', 'settlement_adjustment_actor_fk')->references('id')->on('users')->nullOnDelete();
            });
        }
        if (! Schema::hasIndex('courier_settlement_adjustments', 'settlement_adjustment_reference_unique')) {
            Schema::table('courier_settlement_adjustments', function (Blueprint $table): void {
                $table->unique(['courier_settlement_line_id', 'reference'], 'settlement_adjustment_reference_unique');
            });
        }
    }

    public function down(): void
    {
        foreach (['courier_settlement_adjustments', 'courier_settlement_lines', 'courier_settlements', 'courier_return_receipts', 'shipments', 'dispatch_manifests', 'couriers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
