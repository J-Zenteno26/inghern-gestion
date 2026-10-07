<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizacion_bloques', function (Blueprint $table) {
            $table->id();
            $table
                ->foreignId('cotizacion_revision_id')
                ->constrained('cotizacion_revisiones')
                ->cascadeOnDelete();
            $table->string('tipo', 50);
            $table->string('titulo', 180);
            $table->longText('contenido')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('visible')->default(true);
            $table->timestamps();
            $table->index(['cotizacion_revision_id', 'orden']);
        });

        Schema::create('cotizacion_plazos', function (Blueprint $table) {
            $table->id();
            $table
                ->foreignId('cotizacion_revision_id')
                ->constrained('cotizacion_revisiones')
                ->cascadeOnDelete();
            $table->string('hito', 180);
            $table->unsignedSmallInteger('duracion');
            $table->string('unidad', 20)->default('dias');
            $table->string('condicion_inicio', 180)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_plazos');
        Schema::dropIfExists('cotizacion_bloques');
    }
};
