<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_page_stats', function (Blueprint $table): void {
            $table->char('bucket', 64)->primary();
            $table->date('day')->index();
            $table->string('page', 255);
            $table->string('source', 120);
            $table->string('device', 20);
            $table->string('platform', 30);
            $table->string('browser', 30);
            $table->unsignedBigInteger('views')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_page_stats');
    }
};
