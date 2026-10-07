<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicio_planta', function (Blueprint $table) {
            $table
                ->foreignId('servicio_id')
                ->constrained('servicios')
                ->cascadeOnDelete();
            $table
                ->foreignId('planta_id')
                ->constrained('plantas')
                ->restrictOnDelete();
            $table->primary(['servicio_id', 'planta_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio_planta');
    }
};
