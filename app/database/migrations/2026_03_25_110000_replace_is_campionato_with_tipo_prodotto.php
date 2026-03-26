<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('is_campionato');
            // campionato = prezzo/spec definiti (divani, sedie, composizioni bloccate)
            // a_listino  = prodotto finito a prezzo fisso non configurabile (tavoli, sedie standard)
            $table->string('tipo_prodotto', 50)->nullable()->after('is_featured')
                  ->comment('campionato | a_listino');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('tipo_prodotto');
            $table->boolean('is_campionato')->default(false)->after('is_featured');
        });
    }
};
