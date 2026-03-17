<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Change materials from string to json (no data to migrate yet)
            $table->json('materials')->nullable()->change();

            // New fields from supplier crawl analysis
            $table->string('collection')->nullable()->after('brand');
            $table->json('finishes')->nullable()->after('colors');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('materials')->nullable()->change();
            $table->dropColumn(['collection', 'finishes']);
        });
    }
};
