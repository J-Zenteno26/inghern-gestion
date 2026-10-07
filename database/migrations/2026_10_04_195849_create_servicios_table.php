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
        Schema::create('servicios', function (Blueprint $table) {
            $table->id();
            $table
                ->foreignId('cliente_id')
                ->constrained('clientes')
                ->restrictOnDelete();
            $table
                ->foreignId('tipo_servicio_id')
                ->nullable()
                ->constrained('tipo_servicios')
                ->nullOnDelete();
            $table
                ->foreignId('creado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('codigo', 40)->unique();
            $table->string('nombre', 180);
            $table->text('descripcion')->nullable();
            $table->string('estado', 30)->default('prospecto')->index();
            $table->date('fecha_inicio_estimada')->nullable();
            $table->date('fecha_termino_estimada')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['cliente_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servicios');
    }
};
