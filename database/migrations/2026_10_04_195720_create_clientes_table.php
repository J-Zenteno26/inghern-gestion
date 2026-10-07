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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('razon_social', 180);
            $table->string('nombre_fantasia', 180)->nullable();
            $table->string('identificador_tributario', 30)->unique();
            $table->string('email_facturacion')->nullable();
            $table->string('telefono', 40)->nullable();
            $table->string('direccion')->nullable();
            $table->string('comuna', 100)->nullable();
            $table->string('ciudad', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('estado', 30)->default('activo')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
