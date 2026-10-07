<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('partidas_cotizacion', function (Blueprint $table) {
            $table->id();
            $table
                ->foreignId('cotizacion_revision_id')
                ->constrained('cotizacion_revisiones')
                ->cascadeOnDelete();
            $table->string('clase', 40)->default('servicio');
            $table->text('descripcion');
            $table->decimal('cantidad', 12, 3)->default(1);
            $table->string('unidad', 30)->default('servicio');
            $table->decimal('precio_unitario', 15, 2)->default(0);
            $table->decimal('monto_neto', 15, 2)->default(0);
            $table->string('metodo_precio', 40)->default('a_criterio');
            $table->decimal('monto_sugerido', 15, 2)->nullable();
            $table->decimal('monto_final', 15, 2)->default(0);
            $table->text('justificacion_ajuste')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['cotizacion_revision_id', 'orden']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partidas_cotizacion');
    }
};
