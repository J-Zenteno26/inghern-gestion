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
        Schema::create('operaciones_factoring', function (Blueprint $table) {
            $table->id();
            $table
                ->foreignId('factura_id')
                ->constrained('facturas')
                ->cascadeOnDelete();
            $table->string('codigo', 40)->unique();
            $table->string('entidad_factoring', 150)->nullable();
            $table->date('fecha_solicitud');
            $table->decimal('monto_solicitado', 15, 2)->nullable();
            $table->decimal('porcentaje_anticipo', 5, 2)->nullable();
            $table->decimal('monto_adelantado', 15, 2)->nullable();
            $table->decimal('costo_factoring', 15, 2)->nullable();
            $table->date('fecha_abono')->nullable();
            $table->date('fecha_liquidacion_estimada')->nullable();
            $table->date('fecha_liquidacion_real')->nullable();
            $table->string('estado', 30);
            $table->text('observacion')->nullable();
            $table
                ->foreignId('usuario_creador_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index(['factura_id', 'estado']);
            $table->index(['estado', 'fecha_solicitud']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operaciones_factoring');
    }
};
