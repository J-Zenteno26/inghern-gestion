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
        Schema::create('cotizacion_revisiones', function (Blueprint $table) {
            $table->id();
            $table
                ->foreignId('cotizacion_id')
                ->constrained('cotizaciones')
                ->cascadeOnDelete();
            $table
                ->foreignId('contacto_id')
                ->nullable()
                ->constrained('contactos')
                ->nullOnDelete();
            $table
                ->foreignId('creado_por')
                ->constrained('users')
                ->restrictOnDelete();
            $table->unsignedSmallInteger('revision')->default(1);
            $table->string('titulo', 200);
            $table->date('fecha_emision')->nullable();
            $table->char('moneda', 3)->default('CLP');
            $table->decimal('iva_porcentaje', 5, 2)->default(19);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('iva', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('estado', 30)->default('borrador')->index();
            $table->json('cliente_snapshot')->nullable();
            $table->json('contacto_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['cotizacion_id', 'revision']);
        });

        Schema::table('cotizaciones', function (Blueprint $table) {
            $table
                ->foreignId('revision_actual_id')
                ->nullable()
                ->after('estado')
                ->constrained('cotizacion_revisiones')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revision_actual_id');
        });
        Schema::dropIfExists('cotizacion_revisiones');
    }
};
