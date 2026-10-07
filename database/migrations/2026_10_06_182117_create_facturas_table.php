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
        Schema::create('facturas', function (Blueprint $table) {
            $table->id();
            $table
                ->foreignId('orden_compra_id')
                ->constrained('ordenes_compra')
                ->cascadeOnDelete();
            $table
                ->foreignId('cliente_id')
                ->constrained('clientes')
                ->restrictOnDelete();
            $table
                ->foreignId('creado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('folio', 100);
            $table->date('fecha_emision');
            $table->decimal('monto_neto', 15, 2);
            $table->decimal('iva_porcentaje', 5, 2);
            $table->decimal('iva', 15, 2);
            $table->decimal('total', 15, 2);
            $table->char('moneda', 3);
            $table->string('estado', 30)->default('emitida');
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique(['cliente_id', 'folio']);
            $table->index(['orden_compra_id', 'fecha_emision']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facturas');
    }
};
