<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('revision_servicios', function (Blueprint $table) {
            $table
                ->foreignId('catalogo_servicio_id')
                ->nullable()
                ->after('servicio_id')
                ->constrained('catalogo_servicios')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('revision_servicios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('catalogo_servicio_id');
        });
    }
};
