<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogo_servicios', function (Blueprint $table) {
            $table->id();
            $table
                ->foreignId('tipo_servicio_id')
                ->constrained('tipo_servicios')
                ->restrictOnDelete();
            $table->string('codigo', 60)->unique();
            $table->string('nombre', 180);
            $table->text('descripcion')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['tipo_servicio_id', 'nombre']);
            $table->index(['tipo_servicio_id', 'activo', 'orden']);
        });

        Schema::table('servicios', function (Blueprint $table) {
            $table
                ->foreignId('catalogo_servicio_id')
                ->nullable()
                ->constrained('catalogo_servicios')
                ->nullOnDelete();
            $table->string('servicio_otro', 180)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('catalogo_servicio_id');
            $table->dropColumn('servicio_otro');
        });

        Schema::dropIfExists('catalogo_servicios');
    }
};
