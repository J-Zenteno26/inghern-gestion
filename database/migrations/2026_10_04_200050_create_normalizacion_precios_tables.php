<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipo_servicios', function (Blueprint $table) {
            $table
                ->decimal('precio_base', 15, 2)
                ->nullable()
                ->after('descripcion');
            $table
                ->char('moneda_precio', 3)
                ->default('CLP')
                ->after('precio_base');
            $table
                ->string('unidad_precio', 30)
                ->default('servicio')
                ->after('moneda_precio');
        });

        Schema::create('variables_precio', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 60)->unique();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->string('aplica_a', 40)->default('servicio');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('niveles_variable_precio', function (Blueprint $table) {
            $table->id();
            $table
                ->foreignId('variable_precio_id')
                ->constrained('variables_precio')
                ->cascadeOnDelete();
            $table->string('codigo', 50);
            $table->string('nombre', 120);
            $table->text('criterio')->nullable();
            $table->decimal('factor', 8, 4)->default(1);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
            $table->unique(['variable_precio_id', 'codigo']);
        });

        Schema::create('tipo_servicio_variable', function (Blueprint $table) {
            $table
                ->foreignId('tipo_servicio_id')
                ->constrained('tipo_servicios')
                ->cascadeOnDelete();
            $table
                ->foreignId('variable_precio_id')
                ->constrained('variables_precio')
                ->cascadeOnDelete();
            $table->decimal('peso', 5, 4)->default(1);
            $table->boolean('requerida')->default(true);
            $table->primary(['tipo_servicio_id', 'variable_precio_id']);
        });

        Schema::create('revision_valores_variable', function (
            Blueprint $table,
        ) {
            $table->id();
            $table
                ->foreignId('cotizacion_revision_id')
                ->constrained('cotizacion_revisiones')
                ->cascadeOnDelete();
            $table
                ->foreignId('revision_servicio_id')
                ->nullable()
                ->constrained('revision_servicios')
                ->cascadeOnDelete();
            $table
                ->foreignId('variable_precio_id')
                ->nullable()
                ->constrained('variables_precio')
                ->nullOnDelete();
            $table
                ->foreignId('nivel_variable_precio_id')
                ->nullable()
                ->constrained('niveles_variable_precio')
                ->nullOnDelete();
            $table->string('variable_codigo', 60);
            $table->string('nivel_nombre', 120);
            $table->decimal('factor_aplicado', 8, 4)->default(1);
            $table->decimal('peso_aplicado', 5, 4)->default(1);
            $table->string('fuente', 30)->default('sistema');
            $table->text('justificacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revision_valores_variable');
        Schema::dropIfExists('tipo_servicio_variable');
        Schema::dropIfExists('niveles_variable_precio');
        Schema::dropIfExists('variables_precio');
        Schema::table(
            'tipo_servicios',
            fn (Blueprint $table) => $table->dropColumn([
                'precio_base',
                'moneda_precio',
                'unidad_precio',
            ]),
        );
    }
};
