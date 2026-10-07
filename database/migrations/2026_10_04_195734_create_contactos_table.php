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
        Schema::create('contactos', function (Blueprint $table) {
            $table->id();
            $table
                ->foreignId('cliente_id')
                ->constrained('clientes')
                ->restrictOnDelete();
            $table->string('nombre', 150);
            $table->string('cargo', 120)->nullable();
            $table->string('email')->nullable();
            $table->string('telefono', 40)->nullable();
            $table->boolean('es_principal')->default(false);
            $table->string('estado', 30)->default('activo');
            $table->timestamps();

            $table->index(['cliente_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contactos');
    }
};
