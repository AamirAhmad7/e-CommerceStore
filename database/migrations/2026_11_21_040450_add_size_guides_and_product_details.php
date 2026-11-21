<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('size_guides', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('unit', 2);
            $table->string('basis', 20);
            $table->text('note')->nullable();
            $table->json('measurements');
            $table->timestamps();
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('size_guide_id')->nullable()->constrained()->nullOnDelete();
            $table->string('size_guide_mode', 20)->default('shared');
            $table->json('size_guide_override')->nullable();
            foreach (['material', 'lining', 'fit', 'care'] as $field) {
                $table->text($field)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('size_guide_id');
            $table->dropColumn(['size_guide_mode', 'size_guide_override', 'material', 'lining', 'fit', 'care']);
        });
        Schema::dropIfExists('size_guides');
    }
};
