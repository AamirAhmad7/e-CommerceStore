<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $t): void {
            $t->id();
            $t->string('title', 160);
            $t->string('slug', 180)->unique();
            $t->text('introduction')->nullable();
            $t->string('cover_alt', 500)->nullable();
            $t->boolean('is_published')->default(false)->index();
            $t->timestamps();
        });
        Schema::create('collection_items', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('featured_color_id')->nullable()->constrained('product_colors')->nullOnDelete();
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->unique(['collection_id', 'product_id']);
        });
        Schema::create('homepages', function (Blueprint $t): void {
            $t->id();
            $t->string('key')->unique();
            $t->json('draft')->nullable();
            $t->json('published')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepages');
        Schema::dropIfExists('collection_items');
        Schema::dropIfExists('collections');
    }
};
