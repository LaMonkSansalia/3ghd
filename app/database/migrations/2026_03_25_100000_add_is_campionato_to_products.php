<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_campionato')->default(false)->after('is_featured')
                  ->comment('true = prezzo e specifiche definiti (divano, sedia, composizione bloccata); false = configurabile/variabile (cucina su misura, camera)');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('is_campionato');
        });
    }
};
