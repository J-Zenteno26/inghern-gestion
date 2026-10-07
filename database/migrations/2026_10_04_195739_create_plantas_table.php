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
        Schema::create('plantas', function (Blueprint $table) {
            $table->id();
            $table
                ->foreignId('cliente_id')
                ->constrained('clientes')
                ->restrictOnDelete();
            $table->string('nombre', 150);
            $table->string('codigo', 50)->nullable();
            $table->string('direccion')->nullable();
            $table->string('comuna', 100)->nullable();
            $table->string('ciudad', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->text('descripcion')->nullable();
            $table->string('estado', 30)->default('activa');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['cliente_id', 'nombre']);
            $table->index(['cliente_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plantas');
    }
};
