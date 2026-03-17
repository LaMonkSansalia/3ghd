<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('sku')->nullable()->index();
            $table->string('brand')->nullable();
            $table->text('description')->nullable();
            $table->string('materials')->nullable();
            $table->json('colors')->nullable();
            $table->json('dimensions')->nullable();
            $table->decimal('price_list', 10, 2)->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->decimal('markup_override', 5, 4)->nullable();
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            $table->string('source_url')->nullable();
            $table->string('source_file')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->index(['supplier_id', 'is_active']);
            $table->index(['category_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
