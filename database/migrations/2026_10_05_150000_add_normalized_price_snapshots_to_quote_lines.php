<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partidas_cotizacion', function (Blueprint $table) {
            $table
                ->foreignId('catalogo_servicio_id')
                ->nullable()
                ->after('revision_servicio_id')
                ->constrained('catalogo_servicios')
                ->nullOnDelete();
            $table->string('catalogo_nombre_snapshot', 180)->nullable();
            $table->text('catalogo_descripcion_snapshot')->nullable();
            $table->decimal('precio_base_snapshot', 15, 2)->nullable();
            $table->char('moneda_precio_snapshot', 3)->nullable();
            $table->string('unidad_precio_snapshot', 30)->nullable();
            $table->decimal('factor_total_snapshot', 12, 6)->nullable();
            $table->decimal('rango_minimo_snapshot', 15, 2)->nullable();
            $table->decimal('rango_maximo_snapshot', 15, 2)->nullable();
        });

        Schema::table('revision_valores_variable', function (Blueprint $table) {
            $table
                ->foreignId('partida_cotizacion_id')
                ->nullable()
                ->after('revision_servicio_id')
                ->constrained('partidas_cotizacion')
                ->cascadeOnDelete();
            $table->unique(
                ['partida_cotizacion_id', 'variable_precio_id'],
                'revision_valores_partida_variable_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('revision_valores_variable', function (Blueprint $table) {
            $table->dropUnique('revision_valores_partida_variable_unique');
            $table->dropConstrainedForeignId('partida_cotizacion_id');
        });

        Schema::table('partidas_cotizacion', function (Blueprint $table) {
            $table->dropConstrainedForeignId('catalogo_servicio_id');
            $table->dropColumn([
                'catalogo_nombre_snapshot',
                'catalogo_descripcion_snapshot',
                'precio_base_snapshot',
                'moneda_precio_snapshot',
                'unidad_precio_snapshot',
                'factor_total_snapshot',
                'rango_minimo_snapshot',
                'rango_maximo_snapshot',
            ]);
        });
    }
};
