<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table
                ->foreignId('planta_id')
                ->nullable()
                ->after('cliente_id')
                ->constrained('plantas')
                ->restrictOnDelete();

            $table->index(['cliente_id', 'planta_id']);
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropIndex(['cliente_id', 'planta_id']);
            $table->dropConstrainedForeignId('planta_id');
        });
    }
};
