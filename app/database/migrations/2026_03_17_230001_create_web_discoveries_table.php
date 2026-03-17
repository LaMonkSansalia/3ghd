<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_discoveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('entry_url');
            $table->enum('status', ['pending', 'crawling', 'done', 'failed'])->default('pending');
            $table->integer('pages_crawled')->default(0);
            $table->integer('items_found')->default(0);
            $table->integer('items_imported')->default(0);
            $table->json('items')->nullable();         // array of discovered items
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_discoveries');
    }
};
