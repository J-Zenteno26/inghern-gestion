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
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table
                ->foreignId('cliente_id')
                ->constrained('clientes')
                ->restrictOnDelete();
            $table->string('nombre', 180);
            $table->string('nombre_original', 255);
            $table->text('descripcion')->nullable();
            $table->string('tipo_documento', 60);
            $table->string('mime_type', 150);
            $table->string('extension', 20);
            $table->unsignedBigInteger('tamano');
            $table->string('ruta_storage', 500);
            $table
                ->foreignId('usuario_creador_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};
