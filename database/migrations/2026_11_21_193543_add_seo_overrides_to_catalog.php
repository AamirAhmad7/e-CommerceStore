<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'categories', 'collections'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('seo_title', 160)->nullable();
                $table->string('seo_description', 320)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['products', 'categories', 'collections'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['seo_title', 'seo_description']));
        }
    }
};
